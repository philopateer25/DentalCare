<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run 
                            {--no-encrypt : Skip AES-256 encryption}
                            {--only-db : Backup database only}
                            {--only-files : Backup clinical and uploaded files only}
                            {--key= : Custom encryption key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an automated, encrypted backup of the DentalCare clinic database and files';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting DentalCare clinic backup process...');

        $encrypt = !$this->option('no-encrypt');
        $onlyDb = $this->option('only-db');
        $onlyFiles = $this->option('only-files');
        $customKey = $this->option('key');

        $options = [
            'encrypt' => $encrypt,
            'include_db' => !$onlyFiles,
            'include_files' => !$onlyDb,
            'include_env' => !$onlyDb && !$onlyFiles,
        ];

        if ($customKey) {
            $options['key'] = $customKey;
        }

        try {
            $result = $backupService->createBackup($options);

            $this->newLine();
            $this->info('====================================================');
            $this->info('  DENTALCARE BACKUP COMPLETED SUCCESSFULLY');
            $this->info('====================================================');
            $this->line("  File:       <comment>{$result['filename']}</comment>");
            $this->line("  Size:       <comment>{$result['size_formatted']}</comment>");
            $this->line("  Encrypted:  <comment>" . ($result['encrypted'] ? 'YES (AES-256-CBC + HMAC-SHA256)' : 'NO') . "</comment>");
            $this->line("  Checksum:   <comment>{$result['checksum']}</comment>");
            $this->line("  Duration:   <comment>{$result['duration_seconds']}s</comment>");
            $this->line("  Location:   <comment>{$result['path']}</comment>");
            if ($result['pruned_backups'] > 0) {
                $this->line("  Pruned:     <comment>{$result['pruned_backups']} expired backup(s)</comment>");
            }
            $this->info('====================================================');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

