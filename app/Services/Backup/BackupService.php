<?php

namespace App\Services\Backup;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class BackupService
{
    protected DatabaseDumper $dumper;
    protected DatabaseRestorer $restorer;
    protected BackupEncryptor $encryptor;

    public function __construct(DatabaseDumper $dumper, DatabaseRestorer $restorer, BackupEncryptor $encryptor)
    {
        $this->dumper = $dumper;
        $this->restorer = $restorer;
        $this->encryptor = $encryptor;
    }

    /**
     * Get the absolute path to the local backup storage directory.
     */
    public function getBackupDirectory(): string
    {
        $dir = storage_path('app/' . config('backup.directory', 'backups'));

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true, true);
            // Write protection files to block any direct web access
            File::put($dir . '/.htaccess', "Deny from all\n");
            File::put($dir . '/index.html', "");
        }

        return $dir;
    }

    /**
     * Create a complete, protected backup of the clinic system.
     *
     * @param array $options
     * @return array
     * @throws \Throwable
     */
    public function createBackup(array $options = []): array
    {
        $startTime = microtime(true);
        $backupDir = $this->getBackupDirectory();

        $shouldEncrypt = $options['encrypt'] ?? config('backup.encryption.enabled', true);
        $masterKey = $options['key'] ?? config('backup.encryption.key');
        $includeDb = $options['include_db'] ?? config('backup.include.database', true);
        $includeFiles = $options['include_files'] ?? true;
        $includeEnv = $options['include_env'] ?? true;

        if ($shouldEncrypt && empty($masterKey)) {
            throw new RuntimeException("Backup encryption is enabled, but no encryption key is set. Check BACKUP_ENCRYPTION_KEY or APP_KEY.");
        }

        // Unique temporary workspace
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $tempDir = storage_path('app/backup-temp/job_' . $timestamp . '_' . bin2hex(random_bytes(4)));
        File::makeDirectory($tempDir, 0700, true, true);

        $tempZipPath = $tempDir . '/archive.zip';
        $finalFilename = 'dentalcare_backup_' . $timestamp . ($shouldEncrypt ? '.enc' : '.zip');
        $finalFilePath = $backupDir . '/' . $finalFilename;

        try {
            $manifest = [
                'app_name' => config('app.name', 'DentalCare'),
                'created_at' => Carbon::now()->toIso8601String(),
                'version' => '1.0.0',
                'laravel_version' => app()->version(),
                'database_driver' => config('database.default'),
                'encrypted' => $shouldEncrypt,
                'items' => [],
            ];

            // 1. Database Backup
            if ($includeDb) {
                $dbStagingDir = $tempDir . '/database';
                File::makeDirectory($dbStagingDir, 0700, true, true);

                $driver = config('database.default');
                $ext = ($driver === 'sqlite') ? 'sqlite' : 'sql';
                $dbDumpPath = $dbStagingDir . '/database.' . $ext;

                $this->dumper->dump($dbDumpPath);

                $manifest['items']['database'] = [
                    'driver' => $driver,
                    'file' => 'database/database.' . $ext,
                    'size_bytes' => file_exists($dbDumpPath) ? filesize($dbDumpPath) : 0,
                ];
            }

            // 2. Clinical and Uploaded Files Backup
            if ($includeFiles) {
                $filesStagingDir = $tempDir . '/storage';
                File::makeDirectory($filesStagingDir, 0700, true, true);

                $directories = config('backup.include.directories', []);
                $fileCount = 0;
                $totalBytes = 0;

                foreach ($directories as $relativeDir) {
                    $sourcePath = base_path($relativeDir);
                    if (File::isDirectory($sourcePath)) {
                        $targetSubDir = $filesStagingDir . '/' . basename($relativeDir);
                        File::copyDirectory($sourcePath, $targetSubDir);

                        // Count copied files
                        $filesInDir = File::allFiles($targetSubDir);
                        $fileCount += count($filesInDir);
                        foreach ($filesInDir as $f) {
                            $totalBytes += $f->getSize();
                        }
                    }
                }

                // Copy single files (e.g., license.json)
                $singleFiles = config('backup.include.files', []);
                foreach ($singleFiles as $relFile) {
                    $sourceFile = base_path($relFile);
                    if (File::exists($sourceFile)) {
                        $destFile = $tempDir . '/' . basename($relFile);
                        File::copy($sourceFile, $destFile);
                        $fileCount++;
                        $totalBytes += filesize($destFile);
                    }
                }

                $manifest['items']['files'] = [
                    'total_files' => $fileCount,
                    'total_bytes' => $totalBytes,
                ];
            }

            // 3. Environment configuration (.env)
            if ($includeEnv && File::exists(base_path('.env'))) {
                File::copy(base_path('.env'), $tempDir . '/.env');
                $manifest['items']['env'] = true;
            }

            // 4. Save Manifest
            File::put($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // 5. Build ZIP Archive
            $this->zipDirectory($tempDir, $tempZipPath, ['archive.zip']);

            // 6. Protection: Encryption at rest (AES-256-CBC with HMAC-SHA256)
            if ($shouldEncrypt) {
                $this->encryptor->encryptFile($tempZipPath, $finalFilePath, $masterKey);
            } else {
                File::move($tempZipPath, $finalFilePath);
            }

            // Restrict file permissions to owner only (chmod 0600)
            @chmod($finalFilePath, 0600);

            // 7. Generate Standalone SHA-256 Checksum
            $checksum = hash_file('sha256', $finalFilePath);
            File::put($finalFilePath . '.sha256', "{$checksum}  {$finalFilename}\n");

            // 8. Auto-prune old backups according to retention policy
            $deletedCount = $this->cleanOldBackups();

            $elapsed = round(microtime(true) - $startTime, 2);
            $fileSizeBytes = filesize($finalFilePath);

            Log::info("DentalCare backup completed successfully: {$finalFilename} (" . $this->formatBytes($fileSizeBytes) . ") in {$elapsed}s. Encrypted: " . ($shouldEncrypt ? 'YES' : 'NO'));

            return [
                'filename' => $finalFilename,
                'path' => $finalFilePath,
                'size' => $fileSizeBytes,
                'size_formatted' => $this->formatBytes($fileSizeBytes),
                'encrypted' => $shouldEncrypt,
                'checksum' => $checksum,
                'duration_seconds' => $elapsed,
                'pruned_backups' => $deletedCount,
            ];
        } finally {
            // Always clean up the temporary workspace
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * List all available local backups.
     *
     * @return Collection
     */
    public function listBackups(): Collection
    {
        $backupDir = $this->getBackupDirectory();

        if (!File::isDirectory($backupDir)) {
            return collect();
        }

        $files = File::files($backupDir);
        $backups = collect();

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip checksum and hidden files
            if (str_ends_with($filename, '.sha256') || str_starts_with($filename, '.')) {
                continue;
            }

            if (!str_ends_with($filename, '.enc') && !str_ends_with($filename, '.zip')) {
                continue;
            }

            $checksumFile = $file->getRealPath() . '.sha256';
            $checksum = '';

            if (file_exists($checksumFile)) {
                $parts = preg_split('/\s+/', trim(file_get_contents($checksumFile)));
                $checksum = $parts[0] ?? '';
            } else {
                $checksum = hash_file('sha256', $file->getRealPath());
            }

            $isEncrypted = str_ends_with($filename, '.enc') || $this->encryptor->isEncrypted($file->getRealPath());

            $backups->push([
                'filename' => $filename,
                'path' => $file->getRealPath(),
                'size' => $file->getSize(),
                'size_formatted' => $this->formatBytes($file->getSize()),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
                'is_encrypted' => $isEncrypted,
                'checksum' => $checksum,
            ]);
        }

        return $backups->sortByDesc('created_at')->values();
    }

    /**
     * Check if the provided password matches the unique backup delete password
     * (or the authenticated user's account password as fallback).
     */
    public function verifyDeletePassword(?string $password): bool
    {
        if (empty($password)) {
            return false;
        }

        $expectedPassword = config('backup.security.delete_password');
        if (!empty($expectedPassword) && hash_equals($expectedPassword, $password)) {
            return true;
        }

        // Also check against authenticated user's password if available
        $user = auth()->user();
        if ($user && !empty($user->password) && \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            return true;
        }

        return false;
    }

    /**
     * Delete a specific backup file and its checksum.
     * Enforces the unique security password if $enforcePassword is true.
     */
    public function deleteBackup(string $filename, ?string $password = null, bool $enforcePassword = false): bool
    {
        if ($enforcePassword && !$this->verifyDeletePassword($password)) {
            throw new RuntimeException("Invalid deletion password. Deletion rejected for security reasons.");
        }

        $this->validateFilename($filename);
        $backupDir = $this->getBackupDirectory();
        $targetFile = $backupDir . '/' . $filename;
        $checksumFile = $targetFile . '.sha256';

        $deleted = false;

        if (File::exists($targetFile)) {
            $deleted = File::delete($targetFile);
        }

        if (File::exists($checksumFile)) {
            File::delete($checksumFile);
        }

        return $deleted;
    }

    /**
     * Get the absolute path for downloading a backup.
     */
    public function getDownloadPath(string $filename): string
    {
        $this->validateFilename($filename);
        $backupDir = $this->getBackupDirectory();
        $targetFile = $backupDir . '/' . $filename;

        if (!File::exists($targetFile)) {
            throw new RuntimeException("Backup file not found: {$filename}");
        }

        return $targetFile;
    }

    /**
     * Decrypt and extract a backup for recovery/inspection.
     */
    public function extractBackup(string $filename, string $destinationDir, ?string $masterKey = null): array
    {
        $this->validateFilename($filename);
        $backupDir = $this->getBackupDirectory();
        $sourceFile = $backupDir . '/' . $filename;

        if (!File::exists($sourceFile)) {
            throw new RuntimeException("Backup file does not exist: {$filename}");
        }

        $masterKey = $masterKey ?: config('backup.encryption.key');
        $isEncrypted = $this->encryptor->isEncrypted($sourceFile);

        $tempUnpackedZip = storage_path('app/backup-temp/restore_' . bin2hex(random_bytes(6)) . '.zip');
        File::ensureDirectoryExists(dirname($tempUnpackedZip));

        try {
            if ($isEncrypted) {
                if (empty($masterKey)) {
                    throw new RuntimeException("Decryption key required to restore this backup.");
                }
                $this->encryptor->decryptFile($sourceFile, $tempUnpackedZip, $masterKey);
                $zipToExtract = $tempUnpackedZip;
            } else {
                $zipToExtract = $sourceFile;
            }

            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipToExtract) !== true) {
                throw new RuntimeException("Failed to open backup archive for extraction.");
            }

            File::ensureDirectoryExists($destinationDir);
            $zip->extractTo($destinationDir);
            $zip->close();

            $manifestFile = $destinationDir . '/manifest.json';
            $manifest = file_exists($manifestFile) ? json_decode(file_get_contents($manifestFile), true) : [];

            return [
                'success' => true,
                'extracted_to' => $destinationDir,
                'manifest' => $manifest,
            ];
        } finally {
            if (file_exists($tempUnpackedZip)) {
                @unlink($tempUnpackedZip);
            }
        }
    }

    /**
     * Restore the clinic database and/or clinical files from a backup archive.
     *
     * @param string $filename Backup filename (.enc or .zip)
     * @param string|null $masterKey Decryption key if needed
     * @param bool $restoreDatabase Whether to restore the database
     * @param bool $restoreFiles Whether to restore uploaded and clinical files
     * @return array
     * @throws \Throwable
     */
    public function restoreBackup(
        string $filename,
        ?string $masterKey = null,
        bool $restoreDatabase = true,
        bool $restoreFiles = true
    ): array {
        $startTime = microtime(true);
        $tempExtractDir = storage_path('app/backup-temp/restore_exec_' . bin2hex(random_bytes(6)));

        try {
            // 1. Decrypt and extract the backup to temporary staging area
            $extractResult = $this->extractBackup($filename, $tempExtractDir, $masterKey);
            $manifest = $extractResult['manifest'] ?? [];

            $dbRestored = false;
            $filesRestored = 0;

            // 2. Restore Database
            if ($restoreDatabase) {
                $dbFileRelative = $manifest['items']['database']['file'] ?? null;
                $candidatePaths = [];

                if ($dbFileRelative) {
                    $candidatePaths[] = $tempExtractDir . '/' . $dbFileRelative;
                }
                $candidatePaths[] = $tempExtractDir . '/database/database.sqlite';
                $candidatePaths[] = $tempExtractDir . '/database/database.sql';

                $dbDumpPath = null;
                foreach ($candidatePaths as $path) {
                    if (File::exists($path)) {
                        $dbDumpPath = $path;
                        break;
                    }
                }

                if ($dbDumpPath) {
                    $this->restorer->restore($dbDumpPath);
                    $dbRestored = true;
                } else {
                    Log::warning("No database dump found inside backup {$filename} during restore.");
                }
            }

            // 3. Restore Files
            if ($restoreFiles) {
                // Restore storage directories: public, voice_notes, private
                $storageStaging = $tempExtractDir . '/storage';
                if (File::isDirectory($storageStaging)) {
                    $subDirs = File::directories($storageStaging);
                    foreach ($subDirs as $subDir) {
                        $dirName = basename($subDir);
                        $targetDir = storage_path('app/' . $dirName);
                        File::ensureDirectoryExists($targetDir);
                        File::copyDirectory($subDir, $targetDir);
                        $filesRestored += count(File::allFiles($subDir));
                    }
                }

                // Restore license.json if present
                $licenseStaging = $tempExtractDir . '/license.json';
                if (File::exists($licenseStaging)) {
                    File::copy($licenseStaging, storage_path('app/license.json'));
                    $filesRestored++;
                }
            }

            // 4. Clear application caches to reflect restored state immediately
            try {
                \Illuminate\Support\Facades\Artisan::call('cache:clear');
                \Illuminate\Support\Facades\Artisan::call('view:clear');
            } catch (\Throwable $e) {
                // Non-fatal
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("DentalCare backup {$filename} restored successfully in {$duration}s. DB: " . ($dbRestored ? 'YES' : 'NO') . ", Files: {$filesRestored}");

            return [
                'filename' => $filename,
                'database_restored' => $dbRestored,
                'files_restored_count' => $filesRestored,
                'manifest' => $manifest,
                'duration_seconds' => $duration,
            ];
        } finally {
            if (File::exists($tempExtractDir)) {
                File::deleteDirectory($tempExtractDir);
            }
        }
    }

    /**
     * Clean old backups exceeding retention days or count limit.
     */
    public function cleanOldBackups(?int $days = null, ?int $maxCount = null): int
    {
        $days = $days ?? config('backup.retention.days', 30);
        $maxCount = $maxCount ?? config('backup.retention.max_backups', 50);

        $backups = $this->listBackups();
        $cutoffDate = Carbon::now()->subDays($days);
        $deletedCount = 0;

        // 1. Delete backups older than retention cutoff
        foreach ($backups as $backup) {
            if ($backup['created_at']->lessThan($cutoffDate)) {
                $this->deleteBackup($backup['filename']);
                $deletedCount++;
            }
        }

        // 2. If remaining backups still exceed maxCount, prune oldest
        $remaining = $this->listBackups();
        if ($remaining->count() > $maxCount) {
            $toPrune = $remaining->slice($maxCount);
            foreach ($toPrune as $backup) {
                $this->deleteBackup($backup['filename']);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Compress a folder into a ZIP file.
     */
    protected function zipDirectory(string $sourceDir, string $outZipPath, array $excludeBasenames = []): void
    {
        $zip = new ZipArchive();
        if ($zip->open($outZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create zip file at: {$outZipPath}");
        }

        $sourceDir = realpath($sourceDir);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            // Check if file is in exclude list
            if (in_array(basename($filePath), $excludeBasenames, true)) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    /**
     * Sanitize filename to prevent directory traversal attacks.
     */
    protected function validateFilename(string $filename): void
    {
        if (basename($filename) !== $filename || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new RuntimeException("Invalid backup filename specified: {$filename}");
        }
    }

    /**
     * Format byte sizes into human-readable strings.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

