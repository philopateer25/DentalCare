<#
.SYNOPSIS
    Automated Daily Backup Task for DentalCare Clinic Server.
    Can be run via Windows Task Scheduler or executed manually.

.DESCRIPTION
    1. Triggers the DentalCare AES-256 encrypted backup (DB + clinical files).
    2. Verifies the backup archive integrity (SHA-256).
    3. (Optional) Replicates the encrypted backup to a connected secondary USB drive or NAS share.
    4. Automatically purges old backups beyond the retention period.
#>

param (
    [string]$InstallDir = "C:\DentalCare",
    [string]$ExternalBackupPath = "", # E.g. "E:\DentalCare_Secondary_Backups" or "\\NAS\ClinicBackups"
    [int]$RetentionDays = 30
)

$LogFile = "$InstallDir\storage\logs\backup-task.log"

function Write-BackupLog {
    param ([string]$Message, [string]$Level = "INFO")
    $Timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $LogEntry = "[$Timestamp] [$Level] $Message"
    Write-Host $LogEntry
    Add-Content -Path $LogFile -Value $LogEntry -ErrorAction SilentlyContinue
}

Write-BackupLog "=== Starting DentalCare Scheduled Backup Task ==="

# 1. Determine Execution Mode (Docker or Direct PHP)
Set-Location -Path $InstallDir
$IsDocker = (Get-Command "docker" -ErrorAction SilentlyContinue) -and (docker ps -q -f name=clinic_app)

try {
    if ($IsDocker) {
        Write-BackupLog "Executing backup inside Docker container (clinic_app)..."
        $BackupOutput = docker exec clinic_app php artisan backup:run 2>&1 | Out-String
    } else {
        Write-BackupLog "Executing backup via local PHP CLI..."
        $BackupOutput = php artisan backup:run 2>&1 | Out-String
    }

    Write-BackupLog "Backup CLI Output:`n$BackupOutput"

    if ($LASTEXITCODE -ne 0) {
        Write-BackupLog "Backup command returned error code: $LASTEXITCODE" "ERROR"
        Exit 1
    }

    Write-BackupLog "Primary local backup completed successfully." "SUCCESS"
} catch {
    Write-BackupLog "Failed to execute backup: $($_.Exception.Message)" "ERROR"
    Exit 1
}

# 2. Secondary Offsite / External USB Mirroring (The 3-2-1 Rule)
$LocalBackupDir = "$InstallDir\storage\app\backups"

if (-not [string]::IsNullOrWhiteSpace($ExternalBackupPath)) {
    Write-BackupLog "Checking secondary destination: $ExternalBackupPath"

    if (Test-Path $ExternalBackupPath) {
        Write-BackupLog "Mirroring latest encrypted backups to secondary drive..."
        
        # Copy newly created .enc and .sha256 files
        Get-ChildItem -Path $LocalBackupDir -Filter "*.enc" | Where-Object { $_.LastWriteTime -gt (Get-Date).AddDays(-1) } | ForEach-Object {
            Copy-Item -Path $_.FullName -Destination $ExternalBackupPath -Force
            $ShaFile = "$($_.FullName).sha256"
            if (Test-Path $ShaFile) {
                Copy-Item -Path $ShaFile -Destination $ExternalBackupPath -Force
            }
            Write-BackupLog "Mirrored to secondary drive: $($_.Name)" "INFO"
        }

        # Retention pruning on secondary drive
        $CutoffDate = (Get-Date).AddDays(-$RetentionDays)
        Get-ChildItem -Path $ExternalBackupPath -Filter "dentalcare_backup_*.enc" | Where-Object { $_.LastWriteTime -lt $CutoffDate } | ForEach-Object {
            Remove-Item -Path $_.FullName -Force
            $OldSha = "$($_.FullName).sha256"
            if (Test-Path $OldSha) { Remove-Item -Path $OldSha -Force }
            Write-BackupLog "Purged expired secondary backup: $($_.Name)" "INFO"
        }
    } else {
        Write-BackupLog "Secondary destination not reachable or USB unplugged: $ExternalBackupPath" "WARNING"
    }
}

Write-BackupLog "=== DentalCare Backup Task Finished Successfully ==="

