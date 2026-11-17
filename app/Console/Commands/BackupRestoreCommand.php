<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore 
                            {filename : The backup filename (e.g. dentalcare_backup_2026-11-05_235500.enc)}
                            {--target= : Target directory for inspection extraction (if set, does not overwrite live database)}
                            {--key= : Decryption key if different from APP_KEY}
                            {--no-db : Do not restore the database}
                            {--no-files : Do not restore storage files}
                            {--force : Force the restoration without confirmation}';

    protected $description = 'Restore clinic database and files from an encrypted backup archive';

    public function handle(BackupService $backupService): int
    {
        $filename = $this->argument('filename');
        $targetDir = $this->option('target');
        $key = $this->option('key');

        // Inspection mode: extract to target folder
        if ($targetDir) {
            $this->warn("Extracting backup archive for audit/inspection: {$filename}");
            $this->line("Target destination: {$targetDir}");

            try {
                $result = $backupService->extractBackup($filename, $targetDir, $key);
                $this->info('Backup archive extracted successfully!');
                $this->line("Location: <comment>{$result['extracted_to']}</comment>");
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('Failed to extract backup: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // Live system restoration mode
        $this->alert("WARNING: LIVE SYSTEM RESTORE INITIATED");
        $this->warn("Restoring this backup will OVERWRITE the current live database and clinical storage files with the contents of {$filename}.");
        $this->warn("Any clinic data created after this backup was taken will be replaced.");

        if (!$this->option('force') && !$this->confirm('Are you ABSOLUTELY SURE you want to overwrite your live system data with this backup?', false)) {
            $this->info('Restoration cancelled by user.');
            return Command::SUCCESS;
        }

        try {
            $this->info("Restoring DentalCare clinic system from: {$filename}...");
            $restoreDb = !$this->option('no-db');
            $restoreFiles = !$this->option('no-files');

            $result = $backupService->restoreBackup($filename, $key, $restoreDb, $restoreFiles);

            $this->newLine();
            $this->info('====================================================');
            $this->info('  DENTALCARE RESTORE COMPLETED SUCCESSFULLY');
            $this->info('====================================================');
            $this->line("  Archive:    <comment>{$result['filename']}</comment>");
            $this->line("  Database:   <comment>" . ($result['database_restored'] ? 'Restored' : 'Skipped') . "</comment>");
            $this->line("  Files:      <comment>{$result['files_restored_count']} files restored</comment>");
            $this->line("  Duration:   <comment>{$result['duration_seconds']}s</comment>");
            $this->info('====================================================');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Restoration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
