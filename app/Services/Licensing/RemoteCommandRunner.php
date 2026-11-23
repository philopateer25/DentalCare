<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs commands the central app sends inside the signed heartbeat reply.
 *
 * Only the fixed allow-list below can run — there is deliberately no way to
 * execute arbitrary shell commands or code from the central app.
 */
class RemoteCommandRunner
{
    public const ALLOWED = ['sync', 'backup_now', 'clear_cache', 'send_logs', 'update_to_version', 'add_room', 'rename_room', 'set_room_active'];

    /**
     * @param  array<int, mixed>  $commands  [{id, type, args?}, ...]
     * @param  array<int, string>  $alreadyExecuted  ids that already ran
     * @return list<array{id: string, type: string, ok: bool, output: string, finished_at: string}>
     */
    public function run(array $commands, array $alreadyExecuted = []): array
    {
        $results = [];

        foreach ($commands as $command) {
            if (! is_array($command) || empty($command['id']) || empty($command['type'])) {
                continue;
            }

            $id = (string) $command['id'];
            $type = (string) $command['type'];

            if (in_array($id, $alreadyExecuted, true)) {
                continue;
            }

            [$ok, $output] = $this->execute($type, is_array($command['args'] ?? null) ? $command['args'] : []);

            Log::info('Remote license command executed.', ['id' => $id, 'type' => $type, 'ok' => $ok]);

            $results[] = [
                'id' => $id,
                'type' => $type,
                'ok' => $ok,
                'output' => mb_substr($output, 0, 20000),
                'finished_at' => now()->toIso8601String(),
            ];
        }

        return $results;
    }

    /** @return array{0: bool, 1: string} */
    private function execute(string $type, array $args): array
    {
        if (! in_array($type, self::ALLOWED, true)) {
            return [false, "Unknown command '{$type}' — ignored."];
        }

        try {
            return match ($type) {
                // The heartbeat that delivered this command already synced.
                'sync' => [true, 'Synced.'],
                'backup_now' => $this->artisan('backup:run'),
                'clear_cache' => $this->clearCache(),
                'send_logs' => [true, $this->recentErrors()],
                'update_to_version' => [false, 'The automatic updater is not installed on this version yet.'],
                // Rooms / chairs (the chair limit from the license still applies).
                'add_room' => app(ClinicRooms::class)->add($args),
                'rename_room' => app(ClinicRooms::class)->rename($args),
                'set_room_active' => app(ClinicRooms::class)->setActive($args),
            };
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /** @return array{0: bool, 1: string} */
    private function artisan(string $command): array
    {
        $exit = Artisan::call($command);

        return [$exit === 0, trim(Artisan::output())];
    }

    /** @return array{0: bool, 1: string} */
    private function clearCache(): array
    {
        [$ok1, $out1] = $this->artisan('cache:clear');
        [$ok2, $out2] = $this->artisan('view:clear');

        return [$ok1 && $ok2, trim($out1."\n".$out2)];
    }

    /**
     * Headline of the last 50 ERROR/CRITICAL log entries (first line only,
     * no stack traces), to keep patient data out of what we send.
     */
    private function recentErrors(): string
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return 'No log file.';
        }

        // Read only the tail of the file.
        $size = filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - 512 * 1024));
        $tail = stream_get_contents($handle);
        fclose($handle);

        $lines = array_filter(
            preg_split('/\R/', (string) $tail),
            fn (string $line) => preg_match('/^\[[^\]]+\]\s+\w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line) === 1,
        );

        $lines = array_map(fn (string $line) => mb_substr($line, 0, 300), array_slice(array_values($lines), -50));

        return $lines === [] ? 'No recent errors.' : implode("\n", $lines);
    }
}
