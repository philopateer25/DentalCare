<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Tamper-Proof Clock — Session-Based Accumulation with Anti-Rollback OS Clock
 *
 * Tracks real wall-clock elapsed time since the last server sync, surviving
 * app restarts, reboots, and power cycles without trusting the OS clock blindly.
 *
 * Two modes:
 *   1. While app runs  → hrtime() monotonic counter (kernel-level, untamperable)
 *   2. Machine was off  → OS clock gap validated forward-only (rollback = tamper)
 *
 * Total elapsed = Σ(hrtime per session) + Σ(validated OS clock gaps between sessions)
 */
class TamperProofClock
{
    /** @var string Absolute path to the encrypted clock state file. */
    private string $clockFilePath;

    /** @var string Cipher used for file encryption. */
    private string $cipher;

    /** @var string Encryption key derived from APP_KEY. */
    private string $encryptionKey;

    /** @var int|null hrtime(true) captured at session boot (nanoseconds). */
    private ?int $sessionHrtimeStartNs = null;

    /** @var float Accumulated seconds carried over from previous sessions + offline gaps. */
    private float $sessionStartAccumulated = 0.0;

    /** @var bool Whether the clock has been initialised for this process lifecycle. */
    private bool $booted = false;

    /** @var bool Whether tampering was detected. */
    private bool $tamperDetected = false;

    /** @var string|null Description of the tamper that was detected. */
    private ?string $tamperReason = null;

    public function __construct()
    {
        $this->clockFilePath = config('license.clock_file', storage_path('app/license_clock.enc'));
        $this->cipher = config('license.cipher', 'aes-256-gcm');
        $this->encryptionKey = $this->deriveKey();
    }

    // ──────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────

    /**
     * Get the total elapsed seconds since the last server sync.
     *
     * This is the PRIMARY method used by LicenseManager to check whether
     * the grace period has been exceeded.
     */
    public function getElapsedSecondsSinceSync(): float
    {
        $this->ensureBooted();

        if ($this->tamperDetected) {
            // Return a huge value so the grace period is immediately exceeded.
            return PHP_FLOAT_MAX;
        }

        // Accumulated from prior sessions/gaps + hrtime elapsed this session.
        $hrtimeElapsed = (hrtime(true) - $this->sessionHrtimeStartNs) / 1e9;

        return $this->sessionStartAccumulated + $hrtimeElapsed;
    }

    /**
     * Check whether tampering has been detected.
     */
    public function isTampered(): bool
    {
        $this->ensureBooted();

        return $this->tamperDetected;
    }

    /**
     * Get the reason for the tamper flag (if any).
     */
    public function getTamperReason(): ?string
    {
        return $this->tamperReason;
    }

    /**
     * Persist the current clock state to the encrypted file and the DB.
     *
     * Called every N minutes by the LicenseTickCommand scheduler.
     */
    public function tick(): void
    {
        $this->ensureBooted();

        if ($this->tamperDetected) {
            Log::warning('TamperProofClock::tick() called but tamper is flagged — not persisting.');
            return;
        }

        $accumulatedNow = $this->getElapsedSecondsSinceSync();
        $osClockNow = now()->toDateTimeString();

        // Read current state to get the tick sequence.
        $state = $this->readState();
        $nextSeq = ($state['tick_sequence'] ?? 0) + 1;

        // Persist to encrypted file.
        $newState = array_merge($state, [
            'accumulated_seconds_since_sync' => $accumulatedNow,
            'last_os_clock_reading'          => $osClockNow,
            'session_hrtime_start_ns'        => $this->sessionHrtimeStartNs,
            'session_start_accumulated'      => $this->sessionStartAccumulated,
            'tick_sequence'                  => $nextSeq,
        ]);

        $this->writeState($newState);

        // Persist to DB for cross-validation.
        try {
            DB::table('license_clock_ticks')->insert([
                'tick_sequence'       => $nextSeq,
                'accumulated_seconds' => (int) round($accumulatedNow),
                'os_clock_at_tick'    => $osClockNow,
                'created_at'          => $osClockNow,
            ]);
        } catch (\Throwable $e) {
            Log::error('TamperProofClock: failed to write DB tick.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reset the clock after a successful server sync.
     *
     * Called by LicenseManager when a heartbeat or activation succeeds.
     * The server's authoritative time becomes the new anchor.
     */
    public function resetAfterSync(string $serverTime): void
    {
        $this->sessionHrtimeStartNs = hrtime(true);
        $this->sessionStartAccumulated = 0.0;
        $this->tamperDetected = false;
        $this->tamperReason = null;

        $state = [
            'last_sync_server_time'          => $serverTime,
            'accumulated_seconds_since_sync' => 0,
            'last_os_clock_reading'          => now()->toDateTimeString(),
            'session_hrtime_start_ns'        => $this->sessionHrtimeStartNs,
            'session_start_accumulated'      => 0,
            'tick_sequence'                  => 0,
        ];

        $this->writeState($state);

        // Truncate old tick records — they belong to the previous grace window.
        try {
            DB::table('license_clock_ticks')->truncate();
        } catch (\Throwable $e) {
            Log::error('TamperProofClock: failed to truncate ticks after sync.', ['error' => $e->getMessage()]);
        }

        Log::info('TamperProofClock: reset after server sync.', ['server_time' => $serverTime]);
    }

    /**
     * Initialise the clock for first-time use (no prior state exists).
     */
    public function initialise(string $serverTime): void
    {
        $this->resetAfterSync($serverTime);
        $this->booted = true;
    }

    // ──────────────────────────────────────────────
    //  Boot / Session Management
    // ──────────────────────────────────────────────

    /**
     * Boot the clock for the current process if not already done.
     *
     * On each PHP process start (FPM worker, artisan command, etc.) we:
     *   1. Read the persisted state from the encrypted file.
     *   2. Validate the OS clock hasn't been rolled back.
     *   3. Calculate the gap (time the machine was off) using validated OS clock.
     *   4. Start the hrtime() counter for this session.
     */
    private function ensureBooted(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->sessionHrtimeStartNs = hrtime(true);

        $state = $this->readState();

        // No state file exists — this is either first run or the file was deleted.
        if ($state === null) {
            $this->flagTamper('Clock state file missing or corrupted. License re-activation required.');
            return;
        }

        // ── Anti-Rollback Check ──
        $lastOsClockStr = $state['last_os_clock_reading'] ?? null;
        if ($lastOsClockStr === null) {
            $this->flagTamper('Clock state is missing last_os_clock_reading.');
            return;
        }

        try {
            $lastOsClock = new \DateTimeImmutable($lastOsClockStr);
        } catch (\Exception $e) {
            $this->flagTamper('Clock state contains invalid last_os_clock_reading.');
            return;
        }

        $currentOsClock = new \DateTimeImmutable();

        // If the current OS clock is BEFORE the last reading, someone rolled it back.
        if ($currentOsClock < $lastOsClock) {
            $this->flagTamper(sprintf(
                'OS clock rollback detected: current=%s, last_saved=%s',
                $currentOsClock->format('Y-m-d H:i:s'),
                $lastOsClock->format('Y-m-d H:i:s')
            ));
            return;
        }

        // ── Calculate offline gap ──
        $gapSeconds = (float) ($currentOsClock->getTimestamp() - $lastOsClock->getTimestamp());

        // Carry over the accumulated time from the file + the offline gap.
        $previousAccumulated = (float) ($state['accumulated_seconds_since_sync'] ?? 0);
        $this->sessionStartAccumulated = $previousAccumulated + $gapSeconds;

        // ── Cross-validate with DB ticks ──
        $this->crossValidateWithDb($state);
    }

    /**
     * Cross-validate the encrypted file state against the DB tick records.
     *
     * If someone tampered with the file to reduce accumulated_seconds but
     * forgot to also fix the DB (or vice versa), we catch it here.
     */
    private function crossValidateWithDb(array $fileState): void
    {
        try {
            $latestDbTick = DB::table('license_clock_ticks')
                ->orderByDesc('tick_sequence')
                ->first();
        } catch (\Throwable $e) {
            // DB not available — can't cross-validate, but don't flag tamper for DB issues.
            Log::warning('TamperProofClock: DB cross-validation skipped (DB error).', ['error' => $e->getMessage()]);
            return;
        }

        if ($latestDbTick === null) {
            // No DB ticks yet — first boot after sync or fresh install. OK.
            return;
        }

        $fileSeq = (int) ($fileState['tick_sequence'] ?? 0);
        $dbSeq = (int) $latestDbTick->tick_sequence;
        $fileAccum = (float) ($fileState['accumulated_seconds_since_sync'] ?? 0);
        $dbAccum = (float) $latestDbTick->accumulated_seconds;

        // File tick sequence should be >= DB tick sequence.
        // (File is written after DB in tick(), so file seq >= db seq)
        if ($fileSeq < $dbSeq) {
            $this->flagTamper(sprintf(
                'Clock file tick_sequence (%d) is behind DB tick_sequence (%d). File was replaced with an older copy.',
                $fileSeq,
                $dbSeq
            ));
            return;
        }

        // If sequences match, accumulated seconds should be close.
        // Allow tolerance of 2 tick intervals for timing jitter.
        if ($fileSeq === $dbSeq) {
            $toleranceSeconds = config('license.tick_interval_minutes', 10) * 60 * 2;
            if (abs($fileAccum - $dbAccum) > $toleranceSeconds) {
                $this->flagTamper(sprintf(
                    'Accumulated time mismatch: file=%.0fs, db=%.0fs (tolerance=%.0fs).',
                    $fileAccum,
                    $dbAccum,
                    $toleranceSeconds
                ));
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Tamper Flagging
    // ──────────────────────────────────────────────

    private function flagTamper(string $reason): void
    {
        $this->tamperDetected = true;
        $this->tamperReason = $reason;
        $this->sessionStartAccumulated = PHP_FLOAT_MAX;

        Log::critical('TamperProofClock: TAMPER DETECTED', ['reason' => $reason]);
    }

    // ──────────────────────────────────────────────
    //  Encrypted File I/O
    // ──────────────────────────────────────────────

    /**
     * Read and decrypt the clock state file.
     *
     * @return array|null The decoded state, or null if the file is missing / corrupted.
     */
    private function readState(): ?array
    {
        if (!File::exists($this->clockFilePath)) {
            return null;
        }

        try {
            $raw = File::get($this->clockFilePath);
            $decoded = base64_decode($raw, true);

            if ($decoded === false) {
                return null;
            }

            $ivLength = openssl_cipher_iv_length($this->cipher);
            $tagLength = 16; // GCM tag is 16 bytes

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

            $state = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            return is_array($state) ? $state : null;
        } catch (\Throwable $e) {
            Log::error('TamperProofClock: failed to read clock state.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Encrypt and write the clock state to the file.
     */
    private function writeState(array $state): void
    {
        try {
            $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $tag = '';

            $encrypted = openssl_encrypt($json, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);

            if ($encrypted === false) {
                throw new \RuntimeException('openssl_encrypt failed.');
            }

            $packed = base64_encode($iv . $tag . $encrypted);

            File::put($this->clockFilePath, $packed);
        } catch (\Throwable $e) {
            Log::error('TamperProofClock: failed to write clock state.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Derive a 256-bit encryption key from Laravel's APP_KEY.
     */
    private function deriveKey(): string
    {
        $appKey = config('app.key');

        // APP_KEY is typically base64-encoded.
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        // HKDF to derive a purpose-specific key.
        return hash_hkdf('sha256', $appKey, 32, 'dentalcare-license-clock');
    }
}
