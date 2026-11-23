<?php

namespace App\Services;

use App\Services\Licensing\ClinicTelemetry;
use App\Services\Licensing\LicenseEnvelope;
use App\Services\Licensing\RemoteCommandRunner;
use App\Services\Licensing\UnlockCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LicenseManager — Production-grade license management with tamper-proof timing.
 *
 * Responsibilities:
 *  - Activate a license key by verifying it with the Central server.
 *  - Track offline grace period via TamperProofClock (survives reboots).
 *  - Verify the Ed25519-signed license payload from the central app, which
 *    carries the plan, features, rollout stage, paid-until date and commands.
 *  - Decide the application mode: full, read-only or locked.
 *  - Send heartbeats (with health + anonymous usage counts) to re-validate.
 *  - Ensure the user can NEVER read back the stored license key (bcrypt hashed).
 *
 * The license data is stored in an AES-256-GCM encrypted file. The signed
 * envelope is stored as received and re-verified on every read, so editing
 * the file or the database cannot grant features. See docs/licensing-protocol.md.
 */
class LicenseManager
{
    // ── Status constants ──
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_GRACE_WARNING = 'grace_warning';     // 48-66h offline

    public const STATUS_GRACE_URGENT = 'grace_urgent';      // 66-72h offline

    public const STATUS_EXPIRED_GRACE = 'expired_grace';     // >72h offline → lockout

    public const STATUS_SUSPENDED = 'suspended';         // Remotely suspended by dev

    public const STATUS_REVOKED = 'revoked';           // Remotely revoked by dev

    public const STATUS_TAMPERED = 'tampered';          // Tamper detected

    public const STATUS_ERROR = 'error';

    // ── Application modes ──
    public const MODE_FULL = 'full';

    public const MODE_READ_ONLY = 'read_only';

    public const MODE_LOCKED = 'locked';

    private const MODE_RANK = [self::MODE_FULL => 0, self::MODE_READ_ONLY => 1, self::MODE_LOCKED => 2];

    /** Statuses that allow normal application usage. */
    private const ALLOWED_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_GRACE_WARNING,
        self::STATUS_GRACE_URGENT,
    ];

    private string $licenseFilePath;

    private string $cipher;

    private string $encryptionKey;

    private TamperProofClock $clock;

    /** Per-instance caches (the manager is a singleton per request/process). */
    private ?array $licenseCache = null;

    private bool $licenseLoaded = false;

    private ?array $payloadCache = null;

    private bool $payloadLoaded = false;

    private ?array $evaluationCache = null;

    private ?int $evaluatedAt = null;

    public function __construct(TamperProofClock $clock)
    {
        $this->clock = $clock;
        $this->licenseFilePath = config('license.license_file', storage_path('app/license.enc'));
        $this->cipher = config('license.cipher', 'aes-256-gcm');
        $this->encryptionKey = $this->deriveKey();
    }

    // ──────────────────────────────────────────────
    //  Enforcement switch
    // ──────────────────────────────────────────────

    /**
     * Whether license rules apply at all. Never in `local` (developers are
     * never locked out); off in `testing` unless LICENSE_ENFORCE is set.
     */
    public function isEnforced(): bool
    {
        // An explicit LICENSE_ENFORCE wins everywhere, so the real behaviour can be
        // tried on a local Docker install (docs/testing/manual-test-guide.md).
        $flag = config('license.enforce');

        if ($flag !== null && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOL);
        }

        return ! app()->environment('local', 'testing');
    }

    // ──────────────────────────────────────────────
    //  License State (Read)
    // ──────────────────────────────────────────────

    /**
     * Get the current effective license state, factoring in tamper-proof clock.
     *
     * @return array{status: string, elapsed_hours: float, grace_remaining_hours: float, message?: string}
     */
    public function getLicenseState(): array
    {
        // Dev bypass — never lock out in local environment.
        if (app()->environment('local')) {
            return [
                'status' => self::STATUS_ACTIVE,
                'elapsed_hours' => 0,
                'grace_remaining_hours' => (float) config('license.grace_period_hours', 72),
                'message' => 'Development mode — license always active.',
            ];
        }

        // Check for tamper first.
        if ($this->clock->isTampered()) {
            return [
                'status' => self::STATUS_TAMPERED,
                'elapsed_hours' => PHP_FLOAT_MAX,
                'grace_remaining_hours' => 0,
                'message' => 'System integrity check failed. Please connect to the internet and re-activate your license.',
            ];
        }

        // Read the encrypted license file.
        $license = $this->readLicense();

        if ($license === null) {
            return [
                'status' => self::STATUS_INACTIVE,
                'elapsed_hours' => 0,
                'grace_remaining_hours' => 0,
                'message' => 'No license found. Please enter your license key to activate.',
            ];
        }

        // Check if the license was remotely suspended or revoked.
        $storedStatus = $license['status'] ?? self::STATUS_INACTIVE;
        if (in_array($storedStatus, [self::STATUS_SUSPENDED, self::STATUS_REVOKED], true)) {
            return [
                'status' => $storedStatus,
                'elapsed_hours' => 0,
                'grace_remaining_hours' => 0,
                'message' => $storedStatus === self::STATUS_SUSPENDED
                    ? 'Your license has been suspended. Please contact support.'
                    : 'Your license has been revoked. Please contact support for a new key.',
            ];
        }

        // Calculate elapsed time from the tamper-proof clock.
        $elapsedSeconds = $this->clock->getElapsedSecondsSinceSync();
        $elapsedHours = $elapsedSeconds / 3600;

        $gracePeriodHours = (float) config('license.grace_period_hours', 72);
        $warningHours = (float) config('license.alert_warning_hours', 48);
        $urgentHours = (float) config('license.alert_urgent_hours', 66);

        $graceRemaining = max(0, $gracePeriodHours - $elapsedHours);

        // Determine the effective status based on elapsed offline time.
        if ($elapsedHours > $gracePeriodHours) {
            $unlockUntil = $this->offlineUnlockUntil($license);

            if ($unlockUntil !== null) {
                return [
                    'status' => self::STATUS_GRACE_URGENT,
                    'elapsed_hours' => round($elapsedHours, 2),
                    'grace_remaining_hours' => round(max(0, now()->diffInSeconds($unlockUntil, false)) / 3600, 2),
                    'message' => 'Temporary offline unlock until '.$unlockUntil->format('d M Y H:i').'. Please reconnect to the internet.',
                ];
            }

            $status = self::STATUS_EXPIRED_GRACE;
            $message = 'Your offline grace period has expired. Please connect to the internet to continue using the software.';
        } elseif ($elapsedHours > $urgentHours) {
            $status = self::STATUS_GRACE_URGENT;
            $remainingH = round($graceRemaining, 1);
            $message = "URGENT: Connect to the internet within {$remainingH} hours or the software will be locked.";
        } elseif ($elapsedHours > $warningHours) {
            $status = self::STATUS_GRACE_WARNING;
            $remainingH = round($graceRemaining, 1);
            $message = 'You have been offline for '.round($elapsedHours, 1)." hours. Please connect to the internet within {$remainingH} hours.";
        } else {
            $status = self::STATUS_ACTIVE;
            $message = null;
        }

        return [
            'status' => $status,
            'elapsed_hours' => round($elapsedHours, 2),
            'grace_remaining_hours' => round($graceRemaining, 2),
            'message' => $message,
        ];
    }

    /**
     * Get the current alert tier for the UI.
     *
     * @return string|null 'warning', 'urgent', or null (no alert).
     */
    public function getAlertTier(): ?string
    {
        $state = $this->getLicenseState();

        return match ($state['status']) {
            self::STATUS_GRACE_WARNING => 'warning',
            self::STATUS_GRACE_URGENT => 'urgent',
            default => null,
        };
    }

    /**
     * The verified license payload from the central app, or null when there
     * is none or its signature no longer verifies.
     */
    public function getPayload(): ?array
    {
        if ($this->payloadLoaded) {
            return $this->payloadCache;
        }

        $this->payloadLoaded = true;
        $envelope = $this->readLicense()['envelope'] ?? null;

        return $this->payloadCache = $envelope === null
            ? null
            : LicenseEnvelope::verify($envelope, $this->publicKeys());
    }

    // ──────────────────────────────────────────────
    //  Mode (full / read-only / locked) + banners
    // ──────────────────────────────────────────────

    public function getMode(): string
    {
        return $this->evaluate()['mode'];
    }

    /** Human-readable reason for the current restriction (null in full mode). */
    public function getModeReason(): ?string
    {
        return $this->evaluate()['reason'];
    }

    /**
     * Banners to show at the top of every screen.
     *
     * @return list<array{id: string, level: string, message: string}>
     */
    public function getBanners(): array
    {
        return $this->evaluate()['banners'];
    }

    public function isReadOnly(): bool
    {
        return $this->getMode() !== self::MODE_FULL;
    }

    /**
     * @return array{mode: string, reason: ?string, banners: list<array{id: string, level: string, message: string}>}
     */
    private function evaluate(): array
    {
        // Re-evaluate at most once a minute per process.
        $nowTs = now()->getTimestamp();
        if ($this->evaluationCache !== null && $this->evaluatedAt !== null && abs($nowTs - $this->evaluatedAt) < 60) {
            return $this->evaluationCache;
        }

        $this->evaluatedAt = $nowTs;

        if (! $this->isEnforced()) {
            return $this->evaluationCache = ['mode' => self::MODE_FULL, 'reason' => null, 'banners' => []];
        }

        $mode = self::MODE_FULL;
        $reason = null;
        $banners = [];

        $restrict = function (string $newMode, string $why) use (&$mode, &$reason): void {
            if (self::MODE_RANK[$newMode] > self::MODE_RANK[$mode]) {
                $mode = $newMode;
                $reason = $why;
            }
        };

        $state = $this->getLicenseState();
        $status = $state['status'];

        if (in_array($status, [self::STATUS_TAMPERED, self::STATUS_INACTIVE, self::STATUS_REVOKED, self::STATUS_EXPIRED_GRACE], true)) {
            $restrict(self::MODE_LOCKED, $state['message'] ?? 'License is not active.');
        } elseif (in_array($status, [self::STATUS_GRACE_WARNING, self::STATUS_GRACE_URGENT], true) && ! empty($state['message'])) {
            $banners[] = [
                'id' => 'offline',
                'level' => $status === self::STATUS_GRACE_URGENT ? 'danger' : 'warning',
                'message' => $state['message'],
            ];
        }

        $payload = $this->getPayload();

        $finish = function () use (&$mode, &$reason, &$banners): array {
            if ($mode !== self::MODE_FULL) {
                array_unshift($banners, ['id' => 'mode', 'level' => 'danger', 'message' => $reason]);
            }

            return $this->evaluationCache = compact('mode', 'reason', 'banners');
        };

        if ($payload === null) {
            if ($status !== self::STATUS_INACTIVE) {
                $restrict(self::MODE_LOCKED, 'License could not be verified. Please press "Sync now" or contact support.');
            }

            return $finish();
        }

        $now = CarbonImmutable::now();

        // Suspension takes effect from `status_effective_at` (default: the
        // next midnight after it was issued), never in the middle of a day.
        if (($payload['status'] ?? null) === self::STATUS_SUSPENDED) {
            $effectiveAt = $this->parseTime($payload['status_effective_at'] ?? null)
                ?? ($this->parseTime($payload['issued_at'] ?? null) ?? $now)->addDay()->startOfDay();

            if ($now->greaterThanOrEqualTo($effectiveAt)) {
                $restrict(self::MODE_READ_ONLY, 'This account is suspended. You can view and export data. Please contact support.');
            } else {
                $banners[] = [
                    'id' => 'suspension',
                    'level' => 'danger',
                    'message' => 'This account will be suspended on '.$effectiveAt->format('d M Y').'. Please contact support.',
                ];
            }
        }

        // Subscription: warning → read-only → locked, all on midnight boundaries.
        if ($paidUntil = $this->parseTime($payload['paid_until'] ?? null)) {
            $endsAt = $paidUntil->endOfDay();
            $readOnlyFrom = $paidUntil->addDay()->startOfDay()
                ->addDays((int) ($payload['read_only_after_days'] ?? config('license.read_only_after_days', 3)));
            $lockedFrom = $readOnlyFrom->addDays((int) ($payload['lock_after_days'] ?? config('license.lock_after_days', 30)));

            if ($now->greaterThanOrEqualTo($lockedFrom)) {
                $restrict(self::MODE_LOCKED, 'Your subscription ended on '.$paidUntil->format('d M Y').'. Renew to continue. You can still export your data.');
            } elseif ($now->greaterThanOrEqualTo($readOnlyFrom)) {
                $restrict(self::MODE_READ_ONLY, 'Your subscription ended on '.$paidUntil->format('d M Y').'. The system is read-only until it is renewed.');
            } elseif ($now->greaterThan($endsAt)) {
                $banners[] = [
                    'id' => 'billing',
                    'level' => 'danger',
                    'message' => 'Your subscription ended on '.$paidUntil->format('d M Y').'. The system becomes read-only on '.$readOnlyFrom->format('d M Y').'.',
                ];
            } elseif ($now->greaterThanOrEqualTo($endsAt->subDays((int) config('license.billing_warning_days', 7)))) {
                $banners[] = [
                    'id' => 'billing',
                    'level' => 'warning',
                    'message' => 'Your subscription ends on '.$paidUntil->format('d M Y').'. Please renew to avoid interruption.',
                ];
            }
        }

        $announcement = $payload['announcement'] ?? null;
        if (is_array($announcement) && ! empty($announcement['message'])) {
            $banners[] = [
                'id' => 'announcement-'.($announcement['id'] ?? 'current'),
                'level' => in_array($announcement['level'] ?? 'info', ['info', 'warning', 'danger', 'success'], true) ? $announcement['level'] : 'info',
                'message' => (string) $announcement['message'],
            ];
        }

        return $finish();
    }

    // ──────────────────────────────────────────────
    //  Lockout Check
    // ──────────────────────────────────────────────

    /**
     * Check if the application should be completely locked out.
     */
    public function isLockedOut(): bool
    {
        return $this->getMode() === self::MODE_LOCKED;
    }

    /**
     * Check if the license is currently fully active (within grace or synced).
     */
    public function isActive(): bool
    {
        $status = $this->getLicenseState()['status'] ?? self::STATUS_INACTIVE;

        return in_array($status, self::ALLOWED_STATUSES, true);
    }

    // ──────────────────────────────────────────────
    //  Licensed features & settings
    // ──────────────────────────────────────────────

    /**
     * Whether the signed license allows this feature. Always true when the
     * license isn't enforced (local development, tests).
     */
    public function allowsFeature(string $feature): bool
    {
        if (! $this->isEnforced()) {
            return true;
        }

        $payload = $this->getPayload();

        if ($payload === null) {
            return false;
        }

        if (! isset($payload['features']) || ! is_array($payload['features'])) {
            return true;
        }

        return in_array($feature, $payload['features'], true);
    }

    /** Rollout stage set by the central app (1 = appointments only … 4 = everything). */
    public function getRolloutStage(): ?int
    {
        $stage = $this->getPayload()['rollout_stage'] ?? null;

        return $stage === null ? null : (int) $stage;
    }

    /**
     * Chairs (rooms) the license allows, as set in the central app. Null when the
     * license isn't enforced or carries no limit (the practice's own setting applies).
     */
    public function chairLimit(): ?int
    {
        if (! $this->isEnforced()) {
            return null;
        }

        $limit = $this->getPayload()['chair_limit'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /** Stage a module unlocks at (config `license.rollout_stages`; unlisted = 1). */
    public static function stageFor(string $module): int
    {
        return max(1, (int) (config('license.rollout_stages')[$module] ?? 1));
    }

    /**
     * Whether the clinic's rollout stage has reached this module. Always true
     * when the license isn't enforced or the license carries no stage.
     */
    public function stageAllows(string $module): bool
    {
        if (! $this->isEnforced()) {
            return true;
        }

        $stage = $this->getRolloutStage();

        return $stage === null || $stage >= static::stageFor($module);
    }

    /**
     * Summary for the License page.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $license = $this->readLicense() ?? [];
        $payload = $this->getPayload() ?? [];

        return [
            'enforced' => $this->isEnforced(),
            'mode' => $this->getMode(),
            'reason' => $this->getModeReason(),
            'state' => $this->getLicenseState(),
            'clinic_id' => $payload['clinic_id'] ?? $license['clinic_id'] ?? null,
            'package_tier' => $payload['package_tier'] ?? null,
            'features' => $payload['features'] ?? null,
            'rollout_stage' => $payload['rollout_stage'] ?? null,
            'chair_limit' => $payload['chair_limit'] ?? null,
            'xray_limit' => $payload['xray_limit'] ?? null,
            'paid_until' => $payload['paid_until'] ?? null,
            'last_sync_at' => $license['last_server_sync_at'] ?? null,
            'activated_at' => $license['activated_at'] ?? null,
            'offline_unlock_until' => $license['offline_unlock_until'] ?? null,
            'server_url' => $this->serverUrl(),
            'has_license' => $license !== [],
        ];
    }

    // ──────────────────────────────────────────────
    //  License Activation
    // ──────────────────────────────────────────────

    /**
     * Activate a license key by sending it to the Central server.
     *
     * @param  string  $rawKey  The key or enrollment code entered by the user.
     * @return array{success: bool, message: string}
     */
    public function activate(string $rawKey): array
    {
        $serverUrl = $this->serverUrl();

        if (empty($serverUrl)) {
            return ['success' => false, 'message' => 'License server is not configured.'];
        }

        $fingerprint = HardwareFingerprint::generate();
        $nonce = bin2hex(random_bytes(16));

        try {
            $response = $this->http()->post("{$serverUrl}/api/license/activate", [
                'key' => $rawKey,
                'hardware_fingerprint' => $fingerprint,
                'machine_name' => gethostname() ?: 'unknown',
                'app_version' => config('app.version', '1.0.0'),
                'nonce' => $nonce,
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Server rejected the activation request.');

                return ['success' => false, 'message' => $error];
            }

            $payload = $this->acceptEnvelope($response->json('license') ?? $response->json(), $nonce, null);

            if ($payload === null) {
                return ['success' => false, 'message' => 'The license server reply could not be verified.'];
            }

            if (($payload['status'] ?? '') !== self::STATUS_ACTIVE) {
                return ['success' => false, 'message' => $response->json('error') ?? 'License key is not active.'];
            }

            $serverTime = $payload['issued_at'] ?? now()->toIso8601String();

            // Store the license locally (key is hashed — can never be read back).
            $this->writeLicense(array_merge($this->licenseFieldsFromPayload($payload, $response->json('license') ?? $response->json()), [
                'license_key_hash' => Hash::make($rawKey),
                'hardware_fingerprint' => $fingerprint,
                'activated_at' => $serverTime,
                'last_server_sync_at' => $serverTime,
            ]));

            // Initialise the tamper-proof clock.
            $this->clock->initialise($serverTime);

            Log::info('License activated successfully.', ['fingerprint' => $fingerprint, 'clinic_id' => $payload['clinic_id'] ?? null]);

            return ['success' => true, 'message' => 'License activated successfully.'];
        } catch (\Throwable $e) {
            Log::error('License activation failed.', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Could not connect to the license server. Please check your internet connection.'];
        }
    }

    // ──────────────────────────────────────────────
    //  Heartbeat
    // ──────────────────────────────────────────────

    /**
     * Send a heartbeat to the Central server and update local state.
     *
     * @return array{success: bool, message: string, status?: string}
     */
    public function sendHeartbeat(): array
    {
        $serverUrl = $this->serverUrl();

        if (empty($serverUrl)) {
            return ['success' => false, 'message' => 'License server not configured.'];
        }

        $license = $this->readLicense();

        if ($license === null) {
            return ['success' => false, 'message' => 'No license installed.'];
        }

        $nonce = bin2hex(random_bytes(16));
        $telemetry = app(ClinicTelemetry::class);
        $pendingResults = $license['pending_command_results'] ?? [];

        try {
            $response = $this->http()->post("{$serverUrl}/api/license/heartbeat", [
                'clinic_id' => $license['clinic_id'] ?? null,
                'hardware_fingerprint' => HardwareFingerprint::generate(),
                'nonce' => $nonce,
                'app_version' => config('app.version', '1.0.0'),
                'elapsed_hours' => round($this->clock->getElapsedSecondsSinceSync() / 3600, 2),
                'last_issued_at' => $license['issued_at'] ?? null,
                'health' => $telemetry->health(),
                'usage' => $telemetry->usage(),
                'command_results' => $pendingResults,
            ]);

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Server error: '.$response->status()];
            }

            $envelope = $response->json('license') ?? $response->json();
            $payload = $this->acceptEnvelope($envelope, $nonce, $license);

            if ($payload === null) {
                Log::warning('Heartbeat reply failed verification — ignored.');

                return ['success' => false, 'message' => 'The license server reply could not be verified.'];
            }

            $serverStatus = $payload['status'] ?? self::STATUS_INACTIVE;
            $serverTime = $payload['issued_at'] ?? now()->toIso8601String();

            // Handle remote revocation.
            if ($serverStatus === self::STATUS_REVOKED) {
                $this->deleteLicense();

                return ['success' => false, 'message' => 'License has been revoked.', 'status' => $serverStatus];
            }

            $license = array_merge($license, $this->licenseFieldsFromPayload($payload, $envelope), [
                'last_server_sync_at' => $serverTime,
                'pending_command_results' => [],
            ]);
            // A fresh sync replaces any emergency unlock.
            unset($license['offline_unlock_until']);
            $this->writeLicense($license);

            $this->clock->resetAfterSync($serverTime);

            $this->runCommands($payload['commands'] ?? []);

            if ($serverStatus === self::STATUS_SUSPENDED) {
                return ['success' => false, 'message' => 'License has been suspended.', 'status' => $serverStatus];
            }

            return ['success' => true, 'message' => 'Heartbeat successful. Grace period reset.', 'status' => $serverStatus];
        } catch (\Throwable $e) {
            Log::warning('Heartbeat failed — offline.', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Could not reach the license server.'];
        }
    }

    // ──────────────────────────────────────────────
    //  Emergency offline unlock
    // ──────────────────────────────────────────────

    /**
     * Redeem a support-issued unlock code that extends offline use while the
     * clinic's internet is down. It never lifts a suspension or billing lock.
     *
     * @return array{success: bool, message: string}
     */
    public function redeemUnlockCode(string $code): array
    {
        $license = $this->readLicense();
        $payload = $this->getPayload();

        if ($license === null || $payload === null || empty($payload['unlock_secret']) || empty($payload['clinic_id'])) {
            return ['success' => false, 'message' => 'This installation has no verified license to unlock.'];
        }

        $expiresAt = UnlockCode::verify($code, (string) $payload['unlock_secret'], (string) $payload['clinic_id']);

        if ($expiresAt === null) {
            return ['success' => false, 'message' => 'The unlock code is not valid or has expired.'];
        }

        $license['offline_unlock_until'] = $expiresAt->toIso8601String();
        $this->writeLicense($license);

        Log::info('Offline unlock code redeemed.', ['until' => $license['offline_unlock_until']]);

        return ['success' => true, 'message' => 'Unlocked until '.$expiresAt->format('d M Y H:i').'.'];
    }

    // ──────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────

    /**
     * Verify an envelope from the server and check it belongs to this
     * request and this installation. Returns the payload or null.
     */
    private function acceptEnvelope(mixed $envelope, string $nonce, ?array $license): ?array
    {
        $payload = LicenseEnvelope::verify($envelope, $this->publicKeys());

        if ($payload === null) {
            return null;
        }

        // Must answer *this* request — blocks replaying an old "active" reply.
        if (! hash_equals($nonce, (string) ($payload['nonce'] ?? ''))) {
            Log::warning('License reply nonce mismatch.');

            return null;
        }

        // Bound to this machine when the server includes a fingerprint.
        if (! empty($payload['fingerprint']) && ! hash_equals(HardwareFingerprint::generate(), (string) $payload['fingerprint'])) {
            Log::warning('License reply is for a different machine.');

            return null;
        }

        // Must be for the clinic this installation was activated for.
        if ($license !== null && ! empty($license['clinic_id']) && (string) ($payload['clinic_id'] ?? '') !== (string) $license['clinic_id']) {
            Log::warning('License reply is for a different clinic.');

            return null;
        }

        return $payload;
    }

    /** Fields copied into the license file from a verified payload. */
    private function licenseFieldsFromPayload(array $payload, array $envelope): array
    {
        $fields = [
            'envelope' => $envelope,
            'clinic_id' => $payload['clinic_id'] ?? null,
            'issued_at' => $payload['issued_at'] ?? null,
            'status' => ($payload['status'] ?? self::STATUS_ACTIVE) === self::STATUS_REVOKED ? self::STATUS_REVOKED : self::STATUS_ACTIVE,
        ];

        // The central app can move clinics to a new address (e.g. a custom
        // domain) with no visit. Only https is accepted.
        $newUrl = $payload['server_url'] ?? null;
        if (is_string($newUrl) && str_starts_with($newUrl, 'https://') && filter_var($newUrl, FILTER_VALIDATE_URL)) {
            $fields['server_url'] = rtrim($newUrl, '/');
        }

        return $fields;
    }

    /** Run remote commands once each and queue their results for the next heartbeat. */
    private function runCommands(array $commands): void
    {
        if ($commands === []) {
            return;
        }

        $license = $this->readLicense() ?? [];
        $executed = $license['executed_command_ids'] ?? [];

        $results = app(RemoteCommandRunner::class)->run($commands, $executed);

        if ($results === []) {
            return;
        }

        $license = $this->readLicense() ?? $license;
        $license['executed_command_ids'] = array_slice(array_values(array_unique(array_merge(
            $executed,
            array_column($results, 'id'),
        ))), -100);
        $license['pending_command_results'] = array_merge($license['pending_command_results'] ?? [], $results);
        $this->writeLicense($license);
    }

    private function offlineUnlockUntil(array $license): ?CarbonImmutable
    {
        $until = $this->parseTime($license['offline_unlock_until'] ?? null);

        return $until !== null && $until->isFuture() ? $until : null;
    }

    private function serverUrl(): ?string
    {
        return $this->readLicense()['server_url'] ?? (config('license.server_url') ? rtrim(config('license.server_url'), '/') : null);
    }

    /** @return array<string, string> */
    private function publicKeys(): array
    {
        return array_filter((array) config('license.public_keys', []));
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout(15)->acceptJson();
        $apiKey = config('license.api_key');

        return $apiKey ? $request->withHeaders(['X-License-API-Key' => $apiKey]) : $request;
    }

    private function parseTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function forgetCaches(): void
    {
        $this->licenseLoaded = false;
        $this->licenseCache = null;
        $this->payloadLoaded = false;
        $this->payloadCache = null;
        $this->evaluationCache = null;
        $this->evaluatedAt = null;
    }

    // ──────────────────────────────────────────────
    //  Encrypted License File I/O
    // ──────────────────────────────────────────────

    /**
     * Read and decrypt the license file.
     */
    private function readLicense(): ?array
    {
        if ($this->licenseLoaded) {
            return $this->licenseCache;
        }

        $this->licenseLoaded = true;

        return $this->licenseCache = $this->readLicenseFile();
    }

    private function readLicenseFile(): ?array
    {
        if (! File::exists($this->licenseFilePath)) {
            return null;
        }

        try {
            $raw = File::get($this->licenseFilePath);
            $decoded = base64_decode($raw, true);

            if ($decoded === false) {
                return null;
            }

            $ivLength = openssl_cipher_iv_length($this->cipher);
            $tagLength = 16;

            if (strlen($decoded) < $ivLength + $tagLength) {
                return null;
            }

            $iv = substr($decoded, 0, $ivLength);
            $tag = substr($decoded, $ivLength, $tagLength);
            $data = substr($decoded, $ivLength + $tagLength);

            $decrypted = openssl_decrypt($data, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);

            if ($decrypted === false) {
                return null;
            }

            return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('LicenseManager: failed to read license file.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Encrypt and write the license data to the file.
     */
    private function writeLicense(array $data): void
    {
        $this->forgetCaches();

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $tag = '';

            $encrypted = openssl_encrypt($json, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);

            if ($encrypted === false) {
                throw new \RuntimeException('openssl_encrypt failed.');
            }

            File::put($this->licenseFilePath, base64_encode($iv.$tag.$encrypted));
        } catch (\Throwable $e) {
            Log::error('LicenseManager: failed to write license file.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete the license file (used on revocation).
     */
    private function deleteLicense(): void
    {
        $this->forgetCaches();

        if (File::exists($this->licenseFilePath)) {
            File::delete($this->licenseFilePath);
        }

        // Also nuke the clock state.
        $clockPath = config('license.clock_file', storage_path('app/license_clock.enc'));
        if (File::exists($clockPath)) {
            File::delete($clockPath);
        }

        Log::warning('License and clock state files deleted (revocation).');
    }

    /**
     * Derive a 256-bit encryption key from Laravel's APP_KEY.
     */
    private function deriveKey(): string
    {
        $appKey = config('app.key');

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash_hkdf('sha256', $appKey, 32, 'dentalcare-license-data');
    }
}
