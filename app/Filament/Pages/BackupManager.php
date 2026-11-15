<?php

namespace App\Filament\Pages;

use App\Services\Backup\BackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupManager extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Backup & Security';
    protected static ?string $title = 'System Backups & Data Protection';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.backup-manager';

    // Delete Modal State
    public bool $isDeleteModalOpen = false;
    public string $deleteFilename = '';
    public string $deletePassword = '';
    public string $deleteErrorMessage = '';

    // Restore Modal State
    public bool $isRestoreModalOpen = false;
    public string $restoreFilename = '';
    public string $restoreConfirmKeyword = '';
    public string $restoreCustomKey = '';
    public bool $restoreDb = true;
    public bool $restoreFiles = true;
    public string $restoreErrorMessage = '';

    // Upload & Restore Modal State
    public bool $isUploadModalOpen = false;
    public $uploadedBackup = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // Allow clinic administrators, super admins, doctors, and developers
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'doctor']);
        }

        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label('Create Backup Now')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generate Immediate Encrypted Backup')
                ->modalDescription('This will create an atomic snapshot of the entire database and all clinic files (patient documents, voice notes, license, configs) encrypted with AES-256. Proceed?')
                ->modalSubmitActionLabel('Start Backup')
                ->action('createBackupNow'),

            Action::make('openUploadModal')
                ->label('Upload & Restore')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action('openUploadModal'),
        ];
    }

    public function createBackupNow(): void
    {
        try {
            $backupService = app(BackupService::class);
            $result = $backupService->createBackup();

            Notification::make()
                ->title('Backup Created Successfully')
                ->body("Archive: {$result['filename']} ({$result['size_formatted']})")
                ->success()
                ->duration(10000)
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Backup Generation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function downloadBackup(string $filename): ?BinaryFileResponse
    {
        try {
            $backupService = app(BackupService::class);
            $filePath = $backupService->getDownloadPath($filename);

            Notification::make()
                ->title('Download Initiated')
                ->body("Downloading {$filename}...")
                ->info()
                ->send();

            return response()->download($filePath);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Download Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    // --- Delete Modal Management ---

    public function openDeleteModal(string $filename): void
    {
        $this->deleteFilename = $filename;
        $this->deletePassword = '';
        $this->deleteErrorMessage = '';
        $this->isDeleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->isDeleteModalOpen = false;
        $this->deleteFilename = '';
        $this->deletePassword = '';
        $this->deleteErrorMessage = '';
    }

    public function executeDelete(): void
    {
        $backupService = app(BackupService::class);

        if (!$backupService->verifyDeletePassword($this->deletePassword)) {
            $this->deleteErrorMessage = 'Incorrect security password. Deletion rejected.';
            Notification::make()
                ->title('Deletion Rejected')
                ->body('The security password entered is incorrect.')
                ->danger()
                ->send();
            return;
        }

        try {
            $backupService->deleteBackup($this->deleteFilename, $this->deletePassword, true);
            $deletedFile = $this->deleteFilename;
            $this->closeDeleteModal();

            Notification::make()
                ->title('Backup Permanently Deleted')
                ->body("Archive {$deletedFile} was safely removed.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->deleteErrorMessage = $e->getMessage();
            Notification::make()
                ->title('Deletion Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // --- Restore Modal Management ---

    public function openRestoreModal(string $filename): void
    {
        $this->restoreFilename = $filename;
        $this->restoreConfirmKeyword = '';
        $this->restoreCustomKey = '';
        $this->restoreDb = true;
        $this->restoreFiles = true;
        $this->restoreErrorMessage = '';
        $this->isRestoreModalOpen = true;
    }

    public function closeRestoreModal(): void
    {
        $this->isRestoreModalOpen = false;
        $this->restoreFilename = '';
        $this->restoreConfirmKeyword = '';
        $this->restoreCustomKey = '';
        $this->restoreErrorMessage = '';
    }

    public function executeRestore(): void
    {
        if (strtoupper(trim($this->restoreConfirmKeyword)) !== 'RESTORE') {
            $this->restoreErrorMessage = 'Please type RESTORE in capital letters to confirm.';
            return;
        }

        try {
            $backupService = app(BackupService::class);
            $result = $backupService->restoreBackup(
                $this->restoreFilename,
                $this->restoreCustomKey ?: null,
                $this->restoreDb,
                $this->restoreFiles
            );

            $restoredFile = $this->restoreFilename;
            $this->closeRestoreModal();

            Notification::make()
                ->title('System Restored Successfully!')
                ->body("Backup {$restoredFile} was restored. Database: " . ($result['database_restored'] ? 'Restored' : 'Skipped') . " | Files: {$result['files_restored_count']}. Refresh the page to see updated data.")
                ->success()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            $this->restoreErrorMessage = $e->getMessage();
            Notification::make()
                ->title('System Restore Failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    // --- Upload & Restore Modal Management ---

    public function openUploadModal(): void
    {
        $this->uploadedBackup = null;
        $this->isUploadModalOpen = true;
    }

    public function closeUploadModal(): void
    {
        $this->isUploadModalOpen = false;
        $this->uploadedBackup = null;
    }

    public function executeUploadAndRestore(): void
    {
        if (!$this->uploadedBackup) {
            Notification::make()
                ->title('No File Selected')
                ->body('Please choose an encrypted (.enc) or ZIP (.zip) backup file.')
                ->warning()
                ->send();
            return;
        }

        try {
            $backupService = app(BackupService::class);
            $backupDir = $backupService->getBackupDirectory();

            $clientName = $this->uploadedBackup->getClientOriginalName();
            if (!str_ends_with($clientName, '.enc') && !str_ends_with($clientName, '.zip')) {
                throw new \RuntimeException('Invalid file format. Only .enc and .zip backups are supported.');
            }

            // Save uploaded file into backups directory
            $targetPath = $backupDir . '/' . basename($clientName);
            $this->uploadedBackup->storeAs(config('backup.directory', 'backups'), basename($clientName), 'local');

            $this->closeUploadModal();

            // Automatically open restore modal for the newly uploaded backup
            $this->openRestoreModal(basename($clientName));

            Notification::make()
                ->title('Backup Uploaded Successfully')
                ->body("Archive {$clientName} is ready. Confirm below to restore.")
                ->info()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Upload Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getBackupsProperty(): Collection
    {
        return app(BackupService::class)->listBackups();
    }

    public function getTotalSizeProperty(): string
    {
        $bytes = $this->backups->sum('size');
        return app(BackupService::class)->formatBytes($bytes);
    }

    public function getLatestBackupProperty(): ?array
    {
        return $this->backups->first();
    }
}
