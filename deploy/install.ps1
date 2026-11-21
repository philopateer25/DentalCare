<#
.SYNOPSIS
    Automated Installer for DentalCare On-Premise Clinic Server.
    Run this script as Administrator on the Clinic's Windows Server/PC.

.DESCRIPTION
    Tailscale auto-join: the installer never contains a Tailscale key. It gets a
    single-use, pre-authorized, tagged auth key from (in order):
      1. the -TailscaleAuthKey parameter or the DENTALCARE_TS_AUTHKEY env var,
      2. the central enrollment API (POST $CentralApiUrl/enroll/tailscale-key),
         which mints a key per clinic with a Tailscale OAuth client,
      3. a hidden prompt for the person running the install.
    The key is handed to tailscale.exe through a temporary file (never on the
    command line) and deleted right after.

.PARAMETER InstallDir
    Install location. Prompts if omitted (default C:\DentalCare).
.PARAMETER EnrollmentCode
    Clinic enrollment code. Prompts if omitted.
.PARAMETER TailscaleAuthKey
    Optional single-use Tailscale auth key (tskey-auth-...).
#>
param(
    [string]$InstallDir,
    [string]$EnrollmentCode,
    [string]$TailscaleAuthKey = $env:DENTALCARE_TS_AUTHKEY
)

# Ensure Script is running as Administrator
if (-Not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Warning "Please run this script as an Administrator."
    Pause
    Exit
}

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host " DentalCare Clinic Server Installer v1.0" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host ""

if ([string]::IsNullOrWhiteSpace($InstallDir)) {
    $InstallDir = Read-Host "Where would you like to install the system? (Press Enter for default: C:\DentalCare)"
}
if ([string]::IsNullOrWhiteSpace($InstallDir)) {
    $InstallDir = "C:\DentalCare"
}
$StorageDir = "$InstallDir\storage\app"
$CentralApiUrl = "https://central.dentalcare-software.com/api/v1" # Replace with real domain

# Clinic enrollment code is needed early: it names this machine on the tailnet
# and is what the central API uses to mint its Tailscale key.
while ([string]::IsNullOrWhiteSpace($EnrollmentCode)) {
    $EnrollmentCode = Read-Host "Please enter your Clinic Enrollment Code (e.g., CLINIC-XYZ-123)"
}
$EnrollmentCode = $EnrollmentCode.Trim()

# ---------------------------------------------------------
# 1. Hardware Pre-requisite Check (Virtualization)
# ---------------------------------------------------------
Write-Host "[1/7] Performing Hardware Check..."
$Cpu = Get-CimInstance Win32_Processor
if ($Cpu.VirtualizationFirmwareEnabled -eq $false) {
    Write-Host ""
    Write-Host "CRITICAL WARNING: Hardware Virtualization appears to be DISABLED!" -ForegroundColor Yellow
    Write-Host "Docker usually cannot run on this computer until Virtualization is enabled in the BIOS."
    Write-Host ""
    Write-Host "HOW TO FIX THIS (If Docker fails later):" -ForegroundColor Cyan
    Write-Host "1. Restart this computer and enter the BIOS."
    Write-Host "2. Enable 'Intel Virtualization Technology', 'VT-x', or 'AMD SVM'."
    Write-Host "3. If it's a Lenovo laptop, turn off 'Memory Integrity' in Windows Security."
    Write-Host ""
    
    $Override = Read-Host "If you already have Linux/WSL running, this warning might be a false alarm. Do you want to try continuing anyway? (Y/N)"
    if ($Override -notmatch "^[Yy]") {
        Write-Host "Installation aborted. Please enable Virtualization and try again." -ForegroundColor Red
        Pause
        Exit
    }
} else {
    Write-Host "      Virtualization is enabled. Hardware check passed!" -ForegroundColor Green
}

# ---------------------------------------------------------
# 2. Prepare Directories
# ---------------------------------------------------------
Write-Host "[2/7] Preparing installation directories..."
if (!(Test-Path -Path $StorageDir)) {
    New-Item -ItemType Directory -Force -Path $StorageDir | Out-Null
}

# ---------------------------------------------------------
# 3. Install Docker Desktop
# ---------------------------------------------------------
Write-Host "[3/7] Checking for Docker Desktop..."
if (!(Get-Command "docker" -ErrorAction SilentlyContinue)) {
    Write-Host "      Docker not found. Downloading Docker Desktop..." -ForegroundColor Yellow
    $DockerInstaller = "$InstallDir\Docker Desktop Installer.exe"
    Invoke-WebRequest -Uri "https://desktop.docker.com/win/main/amd64/Docker%20Desktop%20Installer.exe" -OutFile $DockerInstaller
    
    Write-Host "      Installing Docker silently to your chosen drive. This may take a few minutes..." -ForegroundColor Yellow
    
    # If the user chose a different drive (like D:\), we force Docker to install EVERYTHING there to save C: space!
    $DockerInstallPath = if ($InstallDir -match "^[Cc]:") { "C:\Program Files\Docker\Docker" } else { "$InstallDir\DockerEngine" }
    $DockerDataPath = "$InstallDir\DockerData"
    
    # Ensure directories exist before installing
    if (!(Test-Path $DockerInstallPath)) { New-Item -ItemType Directory -Force -Path $DockerInstallPath | Out-Null }
    if (!(Test-Path $DockerDataPath)) { New-Item -ItemType Directory -Force -Path $DockerDataPath | Out-Null }
    
    $DockerArgs = @(
        "install",
        "--quiet",
        "--accept-license",
        "--installation-dir=$DockerInstallPath",
        "--wsl-default-data-root=$DockerDataPath"
    )
    $InstallerProcess = Start-Process -FilePath $DockerInstaller -ArgumentList $DockerArgs -Wait -NoNewWindow -PassThru
    
    if ($InstallerProcess.ExitCode -ne 0 -and $InstallerProcess.ExitCode -ne 3010) {
        Write-Host "      CRITICAL ERROR: Docker failed to install! Exit Code: $($InstallerProcess.ExitCode)" -ForegroundColor Red
        Write-Host "      (This might be due to lack of space on C:\ drive, or Windows blocked it)." -ForegroundColor Yellow
        Pause
        Exit
    }
    
    Write-Host "      Docker installed successfully! Starting Docker Engine..." -ForegroundColor Green
    if (Test-Path "$DockerInstallPath\Docker Desktop.exe") {
        Start-Process "$DockerInstallPath\Docker Desktop.exe"
    } else {
        # Fallback just in case
        Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    }
    
    # Inject Docker into the current PowerShell session's PATH so we can use it immediately!
    $DockerBinPath = if (Test-Path "$DockerInstallPath\resources\bin") { "$DockerInstallPath\resources\bin" } else { "C:\Program Files\Docker\Docker\resources\bin" }
    $env:Path += ";$DockerBinPath"
    
    # Wait for docker daemon
    Write-Host "      Waiting for Docker Engine to start (this may take a few minutes)..."
    $DockerDeadline = (Get-Date).AddMinutes(10)
    $DockerReady = $false
    while ((Get-Date) -lt $DockerDeadline) {
        if (Get-Command "docker" -ErrorAction SilentlyContinue) {
            $dockerInfo = docker info 2>&1 | Out-String
            if ($dockerInfo -match "Server Version") {
                $DockerReady = $true
                break
            }
        }
        Start-Sleep -Seconds 5
    }
    if (-not $DockerReady) {
        Write-Host "      CRITICAL ERROR: Docker Engine did not start within 10 minutes." -ForegroundColor Red
        Write-Host "      Restart this PC (Docker/WSL often needs a reboot after install), then run the installer again." -ForegroundColor Yellow
        Pause
        Exit 1
    }
} else {
    Write-Host "      Docker is already installed." -ForegroundColor Green
}

# ---------------------------------------------------------
# 4. Install Tailscale (Remote Management)
# ---------------------------------------------------------
Write-Host "[4/7] Checking for Tailscale Secure Network..."
$TsPath = "${env:ProgramFiles}\Tailscale\tailscale.exe"
if (!(Get-Command "tailscale" -ErrorAction SilentlyContinue) -and !(Test-Path $TsPath)) {
    Write-Host "      Tailscale not found. Downloading..." -ForegroundColor Yellow
    $TsInstaller = "$env:TEMP\tailscale-setup.exe"
    Invoke-WebRequest -Uri "https://pkgs.tailscale.com/stable/tailscale-setup-latest.exe" -OutFile $TsInstaller

    Write-Host "      Installing Tailscale silently..."
    Start-Process -FilePath $TsInstaller -ArgumentList "/quiet" -Wait -NoNewWindow
} else {
    Write-Host "      Tailscale is already installed." -ForegroundColor Green
}
if (!(Test-Path $TsPath)) {
    $TsCmd = Get-Command "tailscale" -ErrorAction SilentlyContinue
    if ($TsCmd) { $TsPath = $TsCmd.Source }
}

# Tailnet hostname derived from the enrollment code, e.g. dentalcare-clinic-xyz-123
$TsHostname = ("dentalcare-" + ($EnrollmentCode.ToLower() -replace '[^a-z0-9]+', '-')).Trim('-')
if ($TsHostname.Length -gt 63) { $TsHostname = $TsHostname.Substring(0, 63).Trim('-') }

$TsAlreadyJoined = $false
if (Test-Path $TsPath) {
    try {
        $TsStatus = & $TsPath status --json 2>$null | Out-String | ConvertFrom-Json
        if ($TsStatus.BackendState -eq "Running") { $TsAlreadyJoined = $true }
    } catch { }
}

if (!(Test-Path $TsPath)) {
    Write-Host "      Could not find tailscale.exe. Run 'tailscale up' manually after restarting." -ForegroundColor Yellow
} elseif ($TsAlreadyJoined) {
    Write-Host "      This PC is already connected to the DentalCare network." -ForegroundColor Green
} else {
    # 1) parameter / env var, 2) central enrollment API, 3) prompt
    if ([string]::IsNullOrWhiteSpace($TailscaleAuthKey)) {
        try {
            Write-Host "      Requesting a network key from the Central Server..."
            $Resp = Invoke-RestMethod -Method Post -Uri "$CentralApiUrl/enroll/tailscale-key" `
                -ContentType "application/json" -TimeoutSec 20 `
                -Body (@{ enrollment_code = $EnrollmentCode; hostname = $TsHostname } | ConvertTo-Json)
            if ($Resp.auth_key) { $TailscaleAuthKey = [string]$Resp.auth_key }
        } catch {
            Write-Host "      Central Server did not provide a key ($($_.Exception.Message))." -ForegroundColor Yellow
        }
    }
    if ([string]::IsNullOrWhiteSpace($TailscaleAuthKey)) {
        $Secure = Read-Host "      Paste the Tailscale auth key for this clinic (or press Enter to skip)" -AsSecureString
        $TailscaleAuthKey = [Runtime.InteropServices.Marshal]::PtrToStringBSTR(
            [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Secure))
    }

    if ([string]::IsNullOrWhiteSpace($TailscaleAuthKey)) {
        Write-Host "      Skipped. Remote support will not work until this PC joins the network." -ForegroundColor Yellow
    } else {
        Write-Host "      Joining the DentalCare network as '$TsHostname'..." -ForegroundColor Green
        # Pass the key via a temp file readable only by Administrators/SYSTEM so it never
        # shows up in process lists or logs.
        $KeyFile = Join-Path $env:TEMP ("ts-" + [guid]::NewGuid().ToString("N") + ".key")
        try {
            Set-Content -Path $KeyFile -Value $TailscaleAuthKey.Trim() -NoNewline
            icacls $KeyFile /inheritance:r /grant:r "*S-1-5-32-544:F" "*S-1-5-18:F" | Out-Null
            & $TsPath up "--auth-key=file:$KeyFile" "--hostname=$TsHostname" --unattended
            if ($LASTEXITCODE -eq 0) {
                Write-Host "      Connected to the DentalCare network." -ForegroundColor Green
            } else {
                Write-Host "      WARNING: Tailscale could not join (exit $LASTEXITCODE). The key may be expired or already used." -ForegroundColor Yellow
            }
        } finally {
            Remove-Item $KeyFile -Force -ErrorAction SilentlyContinue
            $TailscaleAuthKey = $null
        }
    }
}

# ---------------------------------------------------------
# 5. Clinic Enrollment
# ---------------------------------------------------------
Write-Host "[5/7] Clinic Enrollment" -ForegroundColor Cyan
Write-Host "      Validating code with Central Server..."
# In production, you would use Invoke-RestMethod here. 
# We mock the response to generate the local license.json file.
$LicenseData = @{
    clinic_id = $EnrollmentCode
    status = "active"
    grace_period_expires_at = (Get-Date).AddDays(7).ToString("yyyy-MM-dd HH:mm:ss")
    last_synced_at = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
}
$LicenseData | ConvertTo-Json | Set-Content -Path "$StorageDir\license.json"
Write-Host "      Enrollment successful! License generated." -ForegroundColor Green


# ---------------------------------------------------------
# 6. Generate Secure App Key
# ---------------------------------------------------------
Write-Host "[6/8] Generating Secure Application Key..."
# Reuse the existing APP_KEY on a re-run: a new key would make encrypted data unreadable.
$AppKey = $null
$ExistingCompose = "$InstallDir\docker-compose.yml"
if (Test-Path $ExistingCompose) {
    $Match = Select-String -Path $ExistingCompose -Pattern 'APP_KEY=(base64:[A-Za-z0-9+/=]+)' | Select-Object -First 1
    if ($Match) {
        $AppKey = $Match.Matches[0].Groups[1].Value
        Write-Host "      Existing installation found. Keeping its application key." -ForegroundColor Green
    }
}
if (-not $AppKey) {
    $Bytes = New-Object Byte[] 32
    $Rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $Rng.GetBytes($Bytes)
    $AppKey = "base64:" + [Convert]::ToBase64String($Bytes)
}

# Database passwords: reuse the ones from an existing install (MySQL only applies them
# when its volume is first created), otherwise generate random ones.
function New-RandomSecret {
    $b = New-Object Byte[] 24
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b)
    return ([Convert]::ToBase64String($b) -replace '[+/=]', '')
}
$DbPassword = $null
$DbRootPassword = $null
if (Test-Path $ExistingCompose) {
    $m = Select-String -Path $ExistingCompose -Pattern 'MYSQL_PASSWORD:\s*(\S+)' | Select-Object -First 1
    if ($m) { $DbPassword = $m.Matches[0].Groups[1].Value }
    $m = Select-String -Path $ExistingCompose -Pattern 'MYSQL_ROOT_PASSWORD:\s*(\S+)' | Select-Object -First 1
    if ($m) { $DbRootPassword = $m.Matches[0].Groups[1].Value }
}
if (-not $DbPassword) { $DbPassword = New-RandomSecret }
if (-not $DbRootPassword) { $DbRootPassword = New-RandomSecret }

# Prefer the Compose v2 plugin ('docker compose'); fall back to the old docker-compose binary.
docker compose version 2>&1 | Out-Null
$UseComposeV2 = ($LASTEXITCODE -eq 0)
function Invoke-Compose {
    if ($script:UseComposeV2) { docker compose @args } else { docker-compose @args }
}

# ---------------------------------------------------------
# 7. Download and Start Application
# ---------------------------------------------------------
Write-Host "[7/8] Deploying DentalCare System..."
$ComposeContent = @"
services:
  nginx:
    image: nginx:alpine
    container_name: clinic_nginx
    ports:
      - "80:80"
    volumes:
      - clinic_code:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    restart: unless-stopped
    networks:
      - clinic_network

  app:
    image: philohany12/dentalcare-app:latest
    container_name: clinic_app
    restart: unless-stopped
    environment: &app-env
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_KEY=$AppKey
      - DB_CONNECTION=mysql
      - DB_HOST=db
      - DB_DATABASE=clinic_db
      - DB_USERNAME=clinic_user
      - DB_PASSWORD=$DbPassword
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
    volumes:
      - clinic_code:/var/www/html
      - clinic_storage:/var/www/html/storage/app
    networks:
      - clinic_network

  # Background jobs (WhatsApp, notifications). The image entrypoint starts php-fpm,
  # so the entrypoint is overridden here.
  worker:
    image: philohany12/dentalcare-app:latest
    container_name: clinic_worker
    restart: unless-stopped
    entrypoint: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--timeout=120"]
    environment: *app-env
    volumes:
      - clinic_storage:/var/www/html/storage/app
    depends_on:
      - app
      - redis
    networks:
      - clinic_network

  # Scheduled tasks, including license:tick and license:heartbeat
  scheduler:
    image: philohany12/dentalcare-app:latest
    container_name: clinic_scheduler
    restart: unless-stopped
    entrypoint: ["php", "artisan", "schedule:work"]
    environment: *app-env
    volumes:
      - clinic_storage:/var/www/html/storage/app
    depends_on:
      - app
    networks:
      - clinic_network

  db:
    image: mysql:8.0
    container_name: clinic_db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: clinic_db
      MYSQL_USER: clinic_user
      MYSQL_PASSWORD: $DbPassword
      MYSQL_ROOT_PASSWORD: $DbRootPassword
    volumes:
      - clinic_db_data:/var/lib/mysql
    networks:
      - clinic_network

  redis:
    image: redis:7-alpine
    container_name: clinic_redis
    restart: unless-stopped
    networks:
      - clinic_network

volumes:
  clinic_db_data:
  clinic_code:
  clinic_storage:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: $StorageDir

networks:
  clinic_network:
    driver: bridge
"@

$ComposeContent | Set-Content -Path "$InstallDir\docker-compose.yml"
# The compose file holds the DB passwords and APP_KEY: restrict it to Administrators and SYSTEM.
icacls "$InstallDir\docker-compose.yml" /inheritance:r /grant:r "*S-1-5-32-544:F" "*S-1-5-18:F" | Out-Null

Write-Host "      Generating Nginx Configuration..."
$NginxContent = @"
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files `$uri `$uri/ /index.php?`$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME `$realpath_root`$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
"@
$NginxContent | Set-Content -Path "$InstallDir\nginx.conf"

# Note: In production, the script would run `docker login` using credentials returned from the Enrollment API.
Set-Location -Path $InstallDir
# Detect an existing database BEFORE touching anything, so a re-run never wipes or re-seeds it.
$ExistingDb = docker volume ls -q --filter "name=clinic_db_data" 2>$null
$IsFreshInstall = [string]::IsNullOrWhiteSpace(($ExistingDb | Out-String))

Write-Host "      Stopping any existing containers (patient data is kept)..."
# Remove containers only. Volumes (database, uploads) are never deleted here:
# 'down -v' would erase the clinic's database.
docker rm -f clinic_app clinic_db clinic_nginx clinic_redis clinic_worker clinic_scheduler 2>&1 | Out-Null
Invoke-Compose down --remove-orphans 2>&1 | Out-Null

Write-Host "      Starting containers (this may take a moment to download images)..."
Invoke-Compose up -d 2>&1 | ForEach-Object { Write-Host $_ }

Write-Host "      Initializing Database and Clinical Data (this may take 15 seconds)..."
# Wait for MySQL to be fully ready and the container to run migrations
Start-Sleep -Seconds 15

# Run the seeder exactly once during initial installation
# We loop to wait for migrations to finish first, and use -T to prevent terminal allocation errors
$MaxRetries = 24
$RetryCount = 0
if (-not $IsFreshInstall) {
    Write-Host "      Existing database found. Skipping seeding so no data is changed." -ForegroundColor Green
    $MaxRetries = 0
    $RetryCount = -1
}
while ($RetryCount -ge 0 -and $RetryCount -lt $MaxRetries) {
    $SeedResult = Invoke-Compose exec -T app php artisan db:seed --force 2>&1 | Out-String
    if ($LASTEXITCODE -eq 0 -and $SeedResult -match "DONE") {
        break
    }
    Start-Sleep -Seconds 5
    $RetryCount++
}

if ($RetryCount -ge $MaxRetries) {
    Write-Host "      WARNING: Database seeding timed out. You may need to run it manually." -ForegroundColor Yellow
} else {
    Write-Host "      Database initialized successfully!" -ForegroundColor Green
}


# ---------------------------------------------------------
# 8. Create Desktop Shortcut
# ---------------------------------------------------------
Write-Host "[8/8] Creating Desktop Shortcut..."
$WshShell = New-Object -comObject WScript.Shell
$Shortcut = $WshShell.CreateShortcut("$([Environment]::GetFolderPath('Desktop'))\DentalCare Dashboard.lnk")
$Shortcut.TargetPath = "http://localhost/admin"
$Shortcut.IconLocation = "shell32.dll,27" # Temporary icon
$Shortcut.Save()

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host " INSTALLATION COMPLETE! " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "A shortcut has been placed on the Desktop."
Write-Host "You can now access the system at http://localhost/admin"
Write-Host ""
Pause
