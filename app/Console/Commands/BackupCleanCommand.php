<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupCleanCommand extends Command
{
    protected $signature = 'backup:clean 
                            {--days= : Override retention days}
                            {--max= : Override maximum backup count}';
    protected $description = 'Clean up expired backups according to retention policy';

    public function handle(BackupService $backupService): int
    {
        $days = $this->option('days') ? (int) $this->option('days') : null;
        $max = $this->option('max') ? (int) $this->option('max') : null;

        $this->info('Evaluating local backup retention policy...');
        $deleted = $backupService->cleanOldBackups($days, $max);

        $this->info("Pruning complete. {$deleted} expired backup(s) were safely removed.");

        return Command::SUCCESS;
    }
}

