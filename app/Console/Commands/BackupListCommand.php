<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    protected $signature = 'backup:list';
    protected $description = 'List all local backups stored on this device';

    public function handle(BackupService $backupService): int
    {
        $backups = $backupService->listBackups();

        if ($backups->isEmpty()) {
            $this->warn('No local backups found in ' . $backupService->getBackupDirectory());
            return Command::SUCCESS;
        }

        $this->info("Found {$backups->count()} backup(s) in " . $backupService->getBackupDirectory() . ":");

        $rows = $backups->map(function ($b) {
            return [
                $b['filename'],
                $b['size_formatted'],
                $b['created_at']->toDateTimeString(),
                $b['is_encrypted'] ? 'Encrypted (AES-256)' : 'Plain ZIP',
                substr($b['checksum'], 0, 16) . '...',
            ];
        })->toArray();

        $this->table(['Filename', 'Size', 'Created At', 'Protection', 'SHA-256 Checksum'], $rows);

        return Command::SUCCESS;
    }
}

