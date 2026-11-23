<?php

namespace App\Services\Licensing;

use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Health and anonymous usage numbers sent with each heartbeat.
 *
 * Privacy rule: only counts and machine facts — never names, phone numbers,
 * clinical notes or any other patient data.
 */
class ClinicTelemetry
{
    /** @return array<string, mixed> */
    public function health(): array
    {
        return [
            'app_version' => config('app.version', '1.0.0'),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY.' '.php_uname('r'),
            'disk_free_gb' => $this->safe(fn () => round(disk_free_space(storage_path()) / 1024 ** 3, 1)),
            'disk_total_gb' => $this->safe(fn () => round(disk_total_space(storage_path()) / 1024 ** 3, 1)),
            'db_size_mb' => $this->safe(fn () => $this->databaseSizeMb()),
            'last_backup_at' => $this->safe(fn () => app(BackupService::class)->listBackups()->first()['created_at']?->toIso8601String()),
            // Room names only (shown and edited in the central app).
            'rooms' => $this->safe(fn () => app(ClinicRooms::class)->report()),
        ];
    }

    /** @return array<string, int|null> */
    public function usage(): array
    {
        $today = now()->startOfDay();

        return [
            'patients_created_today' => $this->countSince('patients', $today),
            'appointments_created_today' => $this->countSince('appointments', $today),
            'appointments_scheduled_today' => $this->safe(fn () => DB::table('appointments')
                ->whereBetween('start_time', [$today, $today->copy()->endOfDay()])
                ->count()),
            'invoices_created_today' => $this->countSince('invoices', $today),
            'payments_created_today' => $this->countSince('payments', $today),
            'treatment_procedures_created_today' => $this->countSince('treatment_procedures', $today),
            'active_users_today' => $this->safe(fn () => Schema::hasTable('sessions')
                ? DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', $today->getTimestamp())->distinct()->count('user_id')
                : null),
            'total_patients' => $this->safe(fn () => DB::table('patients')->count()),
        ];
    }

    private function countSince(string $table, \DateTimeInterface $since): ?int
    {
        return $this->safe(fn () => DB::table($table)->where('created_at', '>=', $since)->count());
    }

    private function databaseSizeMb(): ?float
    {
        if (config('database.default') !== 'sqlite') {
            return null;
        }

        $path = config('database.connections.sqlite.database');

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $bytes = filesize($path) + (is_file($path.'-wal') ? filesize($path.'-wal') : 0);

        return round($bytes / 1024 ** 2, 1);
    }

    /** Telemetry must never break a heartbeat. */
    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
