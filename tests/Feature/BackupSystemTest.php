<?php

namespace Tests\Feature;

use App\Services\Backup\BackupEncryptor;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupSystemTest extends TestCase
{
    protected string $testBackupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testBackupDir = storage_path('app/backups');
    }

    public function test_can_create_encrypted_backup_with_manifest_and_checksum(): void
    {
        $service = app(BackupService::class);
        $result = $service->createBackup([
            'encrypt' => true,
            'include_db' => true,
            'include_files' => true,
        ]);

        $this->assertFileExists($result['path']);
        $this->assertFileExists($result['path'] . '.sha256');
        $this->assertTrue($result['encrypted']);
        $this->assertNotEmpty($result['checksum']);
        $this->assertGreaterThan(0, $result['size']);

        // Check file header is our encrypted magic header
        $encryptor = app(BackupEncryptor::class);
        $this->assertTrue($encryptor->isEncrypted($result['path']));

        // Clean up
        $service->deleteBackup($result['filename']);
        $this->assertFileDoesNotExist($result['path']);
    }

    public function test_can_extract_and_verify_encrypted_backup(): void
    {
        $service = app(BackupService::class);
        $result = $service->createBackup(['encrypt' => true]);

        $targetDir = storage_path('app/backup-temp/test_extract_' . uniqid());
        $extractResult = $service->extractBackup($result['filename'], $targetDir);

        $this->assertTrue($extractResult['success']);
        $this->assertFileExists($targetDir . '/manifest.json');

        $manifest = json_decode(file_get_contents($targetDir . '/manifest.json'), true);
        $this->assertEquals(config('app.name'), $manifest['app_name']);
        $this->assertTrue($manifest['encrypted']);

        // Clean up
        File::deleteDirectory($targetDir);
        $service->deleteBackup($result['filename']);
    }

    public function test_decryption_with_wrong_key_fails_integrity_check(): void
    {
        $service = app(BackupService::class);
        $result = $service->createBackup(['encrypt' => true]);

        $targetDir = storage_path('app/backup-temp/test_fail_' . uniqid());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Authentication failed');

        try {
            $service->extractBackup($result['filename'], $targetDir, 'completely-wrong-key-123456');
        } finally {
            $service->deleteBackup($result['filename']);
            if (File::exists($targetDir)) {
                File::deleteDirectory($targetDir);
            }
        }
    }

    public function test_delete_backup_enforces_unique_security_password(): void
    {
        $service = app(BackupService::class);
        $result = $service->createBackup(['encrypt' => true]);

        // Wrong password should throw exception
        $failed = false;
        try {
            $service->deleteBackup($result['filename'], 'wrong-password', true);
        } catch (\RuntimeException $e) {
            $failed = true;
            $this->assertStringContainsString('Invalid deletion password', $e->getMessage());
        }
        $this->assertTrue($failed, 'Expected deletion with wrong password to fail');
        $this->assertFileExists($result['path']);

        // Correct password should succeed
        $correctPassword = config('backup.security.delete_password');
        $deleted = $service->deleteBackup($result['filename'], $correctPassword, true);
        $this->assertTrue($deleted);
        $this->assertFileDoesNotExist($result['path']);
    }

    public function test_can_restore_system_from_backup(): void
    {
        $service = app(BackupService::class);
        $result = $service->createBackup(['encrypt' => true]);

        $restoreResult = $service->restoreBackup($result['filename']);

        $this->assertEquals($result['filename'], $restoreResult['filename']);
        $this->assertTrue($restoreResult['database_restored']);
        $this->assertArrayHasKey('files_restored_count', $restoreResult);

        // Clean up
        $service->deleteBackup($result['filename']);
    }

    public function test_artisan_commands_run_successfully(): void
    {
        // Test backup:run
        $this->artisan('backup:run')
            ->assertExitCode(0);

        // Test backup:list
        $this->artisan('backup:list')
            ->assertExitCode(0);

        // Test backup:clean
        $this->artisan('backup:clean --days=999')
            ->assertExitCode(0);

        // Get the latest backup for restore test
        $service = app(BackupService::class);
        $latest = $service->listBackups()->first();

        if ($latest) {
            $this->artisan("backup:restore {$latest['filename']} --force")
                ->assertExitCode(0);
        }
    }
}
