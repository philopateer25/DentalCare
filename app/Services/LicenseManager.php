<?php

namespace App\Services;

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
 *  - Determine lockout status and alert tier for the current request.
 *  - Send heartbeats to the Central server to re-validate.
 *  - Ensure the user can NEVER read back the stored license key (bcrypt hashed).
 *
 * The license data is stored in an AES-256-GCM encrypted file. The user only
 * interacts via a "Enter license key" input — there is no "view key" feature.
 */
class LicenseManager
{
    // ── Status constants ──
    public const STATUS_ACTIVE           = 'active';
    public const STATUS_INACTIVE         = 'inactive';
    public const STATUS_GRACE_WARNING    = 'grace_warning';     // 48-66h offline
    public const STATUS_GRACE_URGENT     = 'grace_urgent';      // 66-72h offline
    public const STATUS_EXPIRED_GRACE    = 'expired_grace';     // >72h offline → lockout
    public const STATUS_SUSPENDED        = 'suspended';         // Remotely suspended by dev
    public const STATUS_REVOKED          = 'revoked';           // Remotely revoked by dev
    public const STATUS_TAMPERED         = 'tampered';          // Tamper detected
    public const STATUS_ERROR            = 'error';

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

    public function __construct(TamperProofClock $clock)
    {
        $this->clock = $clock;
        $this->licenseFilePath = config('license.license_file', storage_path('app/license.enc'));
        $this->cipher = config('license.cipher', 'aes-256-gcm');
        $this->encryptionKey = $this->deriveKey();
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
                'status'                => self::STATUS_ACTIVE,
                'elapsed_hours'         => 0,
                'grace_remaining_hours' => (float) config('license.grace_period_hours', 72),
                'message'               => 'Development mode — license always active.',
            ];
        }

        // Check for tamper first.
        if ($this->clock->isTampered()) {
            return [
                'status'                => self::STATUS_TAMPERED,
                'elapsed_hours'         => PHP_FLOAT_MAX,
                'grace_remaining_hours' => 0,
                'message'               => 'System integrity check failed. Please connect to the internet and re-activate your license.',
            ];
        }

        // Read the encrypted license file.
        $license = $this->readLicense();

        if ($license === null) {
            return [
                'status'                => self::STATUS_INACTIVE,
                'elapsed_hours'         => 0,
                'grace_remaining_hours' => 0,
                'message'               => 'No license found. Please enter your license key to activate.',
            ];
        }

        // Check if the license was remotely suspended or revoked.
        $storedStatus = $license['status'] ?? self::STATUS_INACTIVE;
        if (in_array($storedStatus, [self::STATUS_SUSPENDED, self::STATUS_REVOKED], true)) {
            return [
                'status'                => $storedStatus,
                'elapsed_hours'         => 0,
                'grace_remaining_hours' => 0,
                'message'               => $storedStatus === self::STATUS_SUSPENDED
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
            $status = self::STATUS_EXPIRED_GRACE;
            $message = 'Your offline grace period has expired. Please connect to the internet to continue using the software.';
        } elseif ($elapsedHours > $urgentHours) {
            $status = self::STATUS_GRACE_URGENT;
            $remainingH = round($graceRemaining, 1);
            $message = "URGENT: Connect to the internet within {$remainingH} hours or the software will be locked.";
        } elseif ($elapsedHours > $warningHours) {
            $status = self::STATUS_GRACE_WARNING;
            $remainingH = round($graceRemaining, 1);
            $message = "You have been offline for " . round($elapsedHours, 1) . " hours. Please connect to the internet within {$remainingH} hours.";
        } else {
            $status = self::STATUS_ACTIVE;
            $message = null;
        }

        return [
            'status'                => $status,
            'elapsed_hours'         => round($elapsedHours, 2),
            'grace_remaining_hours' => round($graceRemaining, 2),
            'message'               => $message,
        ];
    }

    /**
     * Get the current alert tier for the UI.
     *
     * @return string|null  'warning', 'urgent', or null (no alert).
     */
    public function getAlertTier(): ?string
    {
        $state = $this->getLicenseState();

        return match ($state['status']) {
            self::STATUS_GRACE_WARNING => 'warning',
            self::STATUS_GRACE_URGENT  => 'urgent',
            default                    => null,
        };
    }

    // ──────────────────────────────────────────────
    //  Lockout Check
    // ──────────────────────────────────────────────

    /**
     * Check if the application should be completely locked out.
     */
    public function isLockedOut(): bool
    {
        if (app()->environment('local', 'testing') || config('app.env') === 'local') {
            return false;
        }

        $status = $this->getLicenseState()['status'] ?? self::STATUS_INACTIVE;

        return !in_array($status, self::ALLOWED_STATUSES, true);
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
    //  License Activation
    // ──────────────────────────────────────────────

    /**
     * Activate a license key by sending it to the Central server.
     *
     * @param  string  $rawKey  The key entered by the user (e.g., "XXXX-XXXX-XXXX-XXXX").
     * @return array{success: bool, message: string}
     */
    public function activate(string $rawKey): array
    {
        $serverUrl = config('license.server_url');
        $apiKey = config('license.api_key');

        if (empty($serverUrl) || empty($apiKey)) {
            return ['success' => false, 'message' => 'License server is not configured.'];
        }

        $fingerprint = HardwareFingerprint::generate();

        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-API-Key' => $apiKey])
                ->post("{$serverUrl}/api/license/activate", [
                    'key'                  => $rawKey,
                    'hardware_fingerprint' => $fingerprint,
                    'machine_name'         => gethostname() ?: 'unknown',
                    'app_version'          => config('app.version', '1.0.0'),
                ]);

            if (!$response->successful()) {
                $error = $response->json('error', 'Server rejected the activation request.');
                return ['success' => false, 'message' => $error];
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'active') {
                return ['success' => false, 'message' => $data['error'] ?? 'License key is not active.'];
            }

            // Store the license locally (key is hashed — can never be read back).
            $serverTime = $data['server_time'] ?? now()->toDateTimeString();

            $this->writeLicense([
                'license_key_hash'     => Hash::make($rawKey),
                'hardware_fingerprint' => $fingerprint,
                'status'               => self::STATUS_ACTIVE,
                'activated_at'         => $serverTime,
                'last_server_sync_at'  => $serverTime,
                'server_signature'     => $data['signature'] ?? '',
            ]);

            // Initialise the tamper-proof clock.
            $this->clock->initialise($serverTime);

            Log::info('License activated successfully.', ['fingerprint' => $fingerprint]);

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
        $serverUrl = config('license.server_url');
        $apiKey = config('license.api_key');

        if (empty($serverUrl) || empty($apiKey)) {
            return ['success' => false, 'message' => 'License server not configured.'];
        }

        $license = $this->readLicense();

        if ($license === null) {
            return ['success' => false, 'message' => 'No license installed.'];
        }

        $fingerprint = HardwareFingerprint::generate();

        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-API-Key' => $apiKey])
                ->post("{$serverUrl}/api/license/heartbeat", [
                    'hardware_fingerprint' => $fingerprint,
                    'app_version'          => config('app.version', '1.0.0'),
                    'elapsed_hours'        => round($this->clock->getElapsedSecondsSinceSync() / 3600, 2),
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Server error: ' . $response->status()];
            }

            $data = $response->json();
            $serverStatus = $data['status'] ?? self::STATUS_INACTIVE;
            $serverTime = $data['server_time'] ?? now()->toDateTimeString();

            // Handle remote suspension or revocation.
            if ($serverStatus === self::STATUS_REVOKED) {
                $this->deleteLicense();
                return ['success' => false, 'message' => 'License has been revoked.', 'status' => $serverStatus];
            }

            if ($serverStatus === self::STATUS_SUSPENDED) {
                $license['status'] = self::STATUS_SUSPENDED;
                $license['last_server_sync_at'] = $serverTime;
                $this->writeLicense($license);
                return ['success' => false, 'message' => 'License has been suspended.', 'status' => $serverStatus];
            }

            // Active — reset the grace clock.
            $license['status'] = self::STATUS_ACTIVE;
            $license['last_server_sync_at'] = $serverTime;
            $this->writeLicense($license);

            $this->clock->resetAfterSync($serverTime);

            return ['success' => true, 'message' => 'Heartbeat successful. Grace period reset.', 'status' => $serverStatus];
        } catch (\Throwable $e) {
            Log::warning('Heartbeat failed — offline.', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the license server.'];
        }
    }

    // ──────────────────────────────────────────────
    //  Encrypted License File I/O
    // ──────────────────────────────────────────────

    /**
     * Read and decrypt the license file.
     */
    private function readLicense(): ?array
    {
        if (!File::exists($this->licenseFilePath)) {
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

            $iv   = substr($decoded, 0, $ivLength);
            $tag  = substr($decoded, $ivLength, $tagLength);
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
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $tag = '';

            $encrypted = openssl_encrypt($json, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);

            if ($encrypted === false) {
                throw new \RuntimeException('openssl_encrypt failed.');
            }

            File::put($this->licenseFilePath, base64_encode($iv . $tag . $encrypted));
        } catch (\Throwable $e) {
            Log::error('LicenseManager: failed to write license file.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete the license file (used on revocation).
     */
    private function deleteLicense(): void
    {
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
