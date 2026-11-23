<#
.SYNOPSIS
    DOXamigo clinic installer (Docker Desktop + Watchtower). Run as Administrator.

.DESCRIPTION
    New install: installs Docker Desktop if needed, asks for the clinic details,
    pulls the DOXamigo images, creates the clinic and its admin (random password,
    shown once), activates the license, opens the firewall for the clinic network.
    Running it again on an installed PC updates the setup and keeps all data.

    Turn it into an .exe with PS2EXE (Win-PS2EXE window, or deploy\docker\build-installer.ps1).
    It is self-contained: the compose file and settings template are inside it.
    Windows PowerShell 5.1 compatible; ASCII only (PS 5.1 reads BOM-less files as ANSI).

    Never runs `docker compose down -v`: -v would delete the clinic's database.
#>

$ErrorActionPreference = 'Stop'

# Everything the installer writes is inside this script, so converting it with
# PS2EXE (Win-PS2EXE or Invoke-ps2exe) needs no other files.
# Edit the clinic compose file / settings template HERE; deploy/docker/README.md explains them.
$ClinicCompose = @'
# DOXamigo on a clinic PC (Docker Desktop). Installed by deploy/install.ps1 as
# C:\DOXamigo\docker-compose.yml next to C:\DOXamigo\.env (settings + secrets).
#
# The code lives ONLY inside the images. Clinic data lives in named volumes
# (database, uploads) and C:\DOXamigo\backups. Watchtower pulls new images at
# night and recreates the containers; the app container then backs up the
# database (if the update changes it), runs migrations and starts again.
#
# Never `docker compose down -v` on a clinic: -v deletes the database volume.

name: doxamigo

x-app: &app
  # One private repository, two kinds of tags: app-<channel> and web-<channel>.
  image: ${DOXAMIGO_IMAGE:-philohany12/dentalcare-app}:app-${DOXAMIGO_CHANNEL:-stable}
  restart: unless-stopped
  env_file: .env
  volumes:
    - storage:/var/www/html/storage
    - data:/var/www/html/database/data
    - ./backups:/var/www/html/storage/app/backups
  labels:
    com.centurylinklabs.watchtower.enable: "true"
  networks: [doxamigo]

services:
  app:
    <<: *app
    container_name: doxamigo_app

  worker:
    <<: *app
    container_name: doxamigo_worker
    user: "www-data"
    command: ["php", "artisan", "queue:work", "database", "--sleep=3", "--tries=3"]
    depends_on: [app]

  scheduler:
    <<: *app
    container_name: doxamigo_scheduler
    user: "www-data"
    # Heartbeat every 30 min, license clock, encrypted backup at 02:00.
    command: ["php", "artisan", "schedule:work"]
    depends_on: [app]

  web:
    image: ${DOXAMIGO_IMAGE:-philohany12/dentalcare-app}:web-${DOXAMIGO_CHANNEL:-stable}
    container_name: doxamigo_web
    restart: unless-stopped
    ports:
      - "${DOXAMIGO_PORT:-80}:80"
    volumes:
      - storage:/var/www/html/storage:ro
    depends_on: [app]
    labels:
      com.centurylinklabs.watchtower.enable: "true"
    networks: [doxamigo]

  watchtower:
    # Maintained fork of containrrr/watchtower (archived; 1.7.1 can't apply updates
    # on Docker Engine 29+). Pinned: change deliberately, after testing an update.
    image: nickfedor/watchtower:1.24.1
    container_name: doxamigo_watchtower
    restart: unless-stopped
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      # config.json with a read-only pull token for the private image registry.
      - ./registry:/config:ro
    environment:
      DOCKER_CONFIG: /config
      # Only update DOXamigo's own containers, never anything else on the PC.
      WATCHTOWER_LABEL_ENABLE: "true"
      # 6-field cron (with seconds), Cairo time: outside clinic hours.
      WATCHTOWER_SCHEDULE: ${DOXAMIGO_UPDATE_SCHEDULE:-0 30 3 * * *}
      TZ: Africa/Cairo
      WATCHTOWER_CLEANUP: "true"
      WATCHTOWER_TIMEOUT: 60s
    networks: [doxamigo]

volumes:
  storage:
  data:

networks:
  doxamigo:
'@

$ClinicEnvTemplate = @'
# DOXamigo clinic (Docker). Written once by install.ps1 to C:\DOXamigo\.env.
# Used by docker compose (DOXAMIGO_* lines) and passed into the containers.
# {{PLACEHOLDERS}} are filled in by install.ps1. Keep this file private.

# --- Images and updates (Watchtower) ---
# One private repository; tags app-<channel> / web-<channel>
DOXAMIGO_IMAGE={{IMAGE}}
# stable = everyone, pilot = clinics that get new versions first
DOXAMIGO_CHANNEL={{CHANNEL}}
DOXAMIGO_PORT={{PORT}}
DOXAMIGO_UPDATE_SCHEDULE=0 30 3 * * *

# --- App ---
APP_NAME=DOXamigo
APP_ENV=production
APP_KEY={{APP_KEY}}
APP_DEBUG=false
APP_TIMEZONE=Africa/Cairo
APP_URL={{APP_URL}}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
CLINIC_LAN_URL={{LAN_URL}}

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/data/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=720
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
MAIL_MAILER=log

# --- License (central app) ---
LICENSE_SERVER_URL={{CENTRAL_URL}}
# Same id for every container, so they count as one PC (see HardwareFingerprint).
LICENSE_MACHINE_ID={{MACHINE_ID}}

BACKUP_DELETE_PASSWORD={{BACKUP_PASSWORD}}
'@

$CentralUrl = 'https://doxamigo-central.philopateerhanyk360.workers.dev'
$DefaultImage = 'philohany12/dentalcare-app'

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Write-Step([string]$text) { Write-Host ""; Write-Host "==> $text" -ForegroundColor Cyan }
function Stop-Install([string]$text) { Write-Host ""; Write-Host $text -ForegroundColor Red; Read-Host 'Press Enter to close'; exit 1 }

function Read-Value([string]$prompt, [string]$default = '', [switch]$Required) {
    while ($true) {
        $suffix = if ($default) { " [$default]" } else { '' }
        $value = Read-Host "$prompt$suffix"
        if (-not $value) { $value = $default }
        $value = $value.Trim()
        if ($value -or -not $Required) { return $value }
        Write-Host '  This is required.' -ForegroundColor Yellow
    }
}

function New-RandomBase64([int]$bytes) {
    $buffer = New-Object byte[] $bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return [Convert]::ToBase64String($buffer)
}

function Get-LanAddress {
    $configs = Get-NetIPConfiguration -ErrorAction SilentlyContinue | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' }
    foreach ($c in $configs) {
        foreach ($ip in $c.IPv4Address) {
            if ($ip.IPAddress -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)') { return $ip.IPAddress }
        }
    }
    return $null
}

function Test-PortFree([int]$p) {
    $listener = $null
    try {
        $listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Any, $p)
        $listener.Start()
        return $true
    } catch { return $false } finally { if ($listener) { $listener.Stop() } }
}

function Invoke-Compose([string[]]$arguments, [switch]$AllowFailure) {
    Push-Location $Root
    try {
        # Out-Host: Docker's output goes to the window, not into the return value.
        & docker compose @arguments | Out-Host
        $code = $LASTEXITCODE
    } finally { Pop-Location }
    if ($code -ne 0 -and -not $AllowFailure) { Stop-Install "docker compose $($arguments -join ' ') failed. Send a photo of this window to DOXamigo support." }
    return $code
}

# ---------------------------------------------------------------------------
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { Stop-Install 'Please run the installer as Administrator (right-click > Run as administrator).' }

Write-Host '=============================================' -ForegroundColor Cyan
Write-Host ' DOXamigo clinic installer' -ForegroundColor Cyan
Write-Host '=============================================' -ForegroundColor Cyan

$Root = Read-Value 'Install folder' 'C:\DOXamigo'
New-Item -ItemType Directory -Force -Path $Root, (Join-Path $Root 'backups'), (Join-Path $Root 'registry') | Out-Null
$EnvFile = Join-Path $Root '.env'
$IsUpdate = Test-Path -LiteralPath $EnvFile

# 1. PC check ----------------------------------------------------------------
Write-Step 'Checking this PC'
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
$cs = Get-CimInstance Win32_ComputerSystem
$virtOk = $cs.HypervisorPresent -or $cpu.VirtualizationFirmwareEnabled
if (-not $virtOk) {
    Write-Host 'Hardware virtualization is OFF, so Docker cannot run on this PC.' -ForegroundColor Yellow
    Write-Host 'Either enable "Intel VT-x" / "AMD SVM" in the BIOS, or install with DOXamigo-Setup.exe (no Docker needed).' -ForegroundColor Yellow
    if ((Read-Host 'Continue anyway? (y/N)') -notmatch '^[Yy]') { exit 1 }
}
$ramGb = [math]::Round($cs.TotalPhysicalMemory / 1GB, 1)
$freeGb = [math]::Round((Get-PSDrive -Name ($Root.Substring(0, 1))).Free / 1GB, 1)
if ($ramGb -lt 6) { Write-Host "Only $ramGb GB of memory: Docker needs about 2 GB on its own; DOXamigo may be slow." -ForegroundColor Yellow }
if ($freeGb -lt 25) { Write-Host "Only $freeGb GB free on the install drive (25 GB recommended)." -ForegroundColor Yellow }

# 2. Docker Desktop -----------------------------------------------------------
Write-Step 'Docker Desktop'
$dockerExe = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    $installer = Join-Path $env:TEMP 'DockerDesktopInstaller.exe'
    Write-Host 'Downloading Docker Desktop (about 500 MB)...'
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -Uri 'https://desktop.docker.com/win/main/amd64/Docker%20Desktop%20Installer.exe' -OutFile $installer -UseBasicParsing
    Write-Host 'Installing Docker Desktop (several minutes)...'
    $p = Start-Process -FilePath $installer -ArgumentList 'install', '--quiet', '--accept-license' -Wait -PassThru
    if ($p.ExitCode -ne 0 -and $p.ExitCode -ne 3010) { Stop-Install "Docker Desktop failed to install (code $($p.ExitCode))." }
    $env:Path += ';C:\Program Files\Docker\Docker\resources\bin'
}
if (Test-Path -LiteralPath $dockerExe) { Start-Process -FilePath $dockerExe -ErrorAction SilentlyContinue }

Write-Host 'Waiting for Docker to start...'
$deadline = (Get-Date).AddMinutes(10)
while ($true) {
    & docker info *> $null
    if ($LASTEXITCODE -eq 0) { break }
    if ((Get-Date) -gt $deadline) { Stop-Install 'Docker did not start within 10 minutes. Restart the PC, open Docker Desktop once, then run this installer again.' }
    Start-Sleep -Seconds 5
}

# Docker Desktop must start with Windows (it starts after this user signs in).
$settings = Join-Path $env:APPDATA 'Docker\settings-store.json'
if (Test-Path -LiteralPath $settings) {
    try {
        $json = Get-Content -LiteralPath $settings -Raw | ConvertFrom-Json
        if ($json.PSObject.Properties['AutoStart']) { $json.AutoStart = $true } else { $json | Add-Member -NotePropertyName AutoStart -NotePropertyValue $true }
        [System.IO.File]::WriteAllText($settings, ($json | ConvertTo-Json -Depth 10), $Utf8NoBom)
    } catch { Write-Host 'Could not switch on "Start Docker Desktop when you sign in"; please tick it in Docker Desktop > Settings.' -ForegroundColor Yellow }
}

# 3. Settings -----------------------------------------------------------------
$Port = 80
$enrollment = ''
if ($IsUpdate) {
    Write-Step 'Existing installation found: updating it (your data is kept)'
    $saved = Select-String -LiteralPath $EnvFile -Pattern '^DOXAMIGO_PORT=(\d+)' | Select-Object -First 1
    if ($saved) { $Port = [int]$saved.Matches[0].Groups[1].Value }
} else {
    Write-Step 'Clinic details'
    $clinicName = (Read-Value 'Clinic name' -Required).Replace('"', '')
    do { $adminEmail = Read-Value 'Admin email (used to log in)' -Required } until ($adminEmail -match '^[^@\s]+@[^@\s]+\.[^@\s]+$')
    $enrollment = Read-Value 'Enrollment code from DOXamigo (DXA-XXXX-XXXX-XXXX), or Enter to skip'
    $registryUser = Read-Value 'Update account (Docker Hub user)' 'philohany12'
    $registryToken = Read-Value 'Update token (read-only, from DOXamigo)' -Required
    $channel = Read-Value 'Update channel (stable / pilot)' 'stable'

    if (-not (Test-PortFree 80)) {
        Write-Host 'Port 80 is used by another program (often IIS or Skype); using 8080.' -ForegroundColor Yellow
        $Port = 8080
    }
    $lan = Get-LanAddress
    $suffix = if ($Port -eq 80) { '' } else { ":$Port" }
    $values = @{
        '{{IMAGE}}'           = $DefaultImage
        '{{CHANNEL}}'         = $channel
        '{{PORT}}'            = "$Port"
        '{{APP_KEY}}'         = 'base64:' + (New-RandomBase64 32)
        '{{APP_URL}}'         = $(if ($lan) { "http://$lan$suffix" } else { "http://localhost$suffix" })
        '{{LAN_URL}}'         = $(if ($lan) { "http://$lan$suffix" } else { '' })
        '{{CENTRAL_URL}}'     = $CentralUrl
        '{{MACHINE_ID}}'      = ((New-RandomBase64 18) -replace '[^A-Za-z0-9]', '')
        '{{BACKUP_PASSWORD}}' = (New-RandomBase64 18)
    }
    $envText = $ClinicEnvTemplate
    foreach ($k in $values.Keys) { $envText = $envText.Replace($k, $values[$k]) }
    [System.IO.File]::WriteAllText($EnvFile, $envText, $Utf8NoBom)

    # Pull credentials: for this install (docker login) and for Watchtower (registry\config.json).
    $auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("$registryUser`:$registryToken"))
    $config = '{ "auths": { "https://index.docker.io/v1/": { "auth": "' + $auth + '" } } }'
    [System.IO.File]::WriteAllText((Join-Path $Root 'registry\config.json'), $config, $Utf8NoBom)
    $registryToken | & docker login -u $registryUser --password-stdin | Out-Null
    if ($LASTEXITCODE -ne 0) { Stop-Install 'The update account or token was not accepted. Check them with DOXamigo and run the installer again.' }
}

# Only DOXamigo's own folders: the settings files are private.
& icacls $EnvFile /inheritance:r /grant:r 'SYSTEM:F' 'Administrators:F' | Out-Null
& icacls (Join-Path $Root 'registry') /inheritance:r /grant:r 'SYSTEM:(OI)(CI)F' 'Administrators:(OI)(CI)F' | Out-Null

[System.IO.File]::WriteAllText((Join-Path $Root 'docker-compose.yml'), $ClinicCompose, $Utf8NoBom)

# 4. Start --------------------------------------------------------------------
Write-Step 'Downloading DOXamigo and starting it (first time: several minutes)'
Invoke-Compose @('pull') | Out-Null
Invoke-Compose @('up', '-d', '--remove-orphans') | Out-Null

Write-Host 'Waiting for the database to be ready...'
$deadline = (Get-Date).AddMinutes(5)
while ((Invoke-Compose @('exec', '-T', 'app', 'php', 'artisan', 'migrate:status') -AllowFailure) -ne 0) {
    if ((Get-Date) -gt $deadline) { Stop-Install 'DOXamigo did not start. Run: docker compose -f C:\DOXamigo\docker-compose.yml logs app   and send the output to support.' }
    Start-Sleep -Seconds 5
}

$login = ''
if (-not $IsUpdate) {
    Write-Step 'Creating the clinic and its admin'
    $file = '/var/www/html/storage/app/first-login.txt'
    Invoke-Compose @('exec', '-T', '-u', 'www-data', 'app', 'php', 'artisan', 'clinic:install', "--clinic=$clinicName", "--admin-email=$adminEmail", "--credentials-file=$file") | Out-Null
    Push-Location $Root
    try { $login = (& docker compose exec -T app cat $file) -join "`r`n"; & docker compose exec -T app rm -f $file | Out-Null } finally { Pop-Location }

    if ($enrollment) {
        Write-Step 'Activating the license'
        if ((Invoke-Compose @('exec', '-T', '-u', 'www-data', 'app', 'php', 'artisan', 'license:activate', $enrollment) -AllowFailure) -ne 0) {
            Write-Host 'Activation failed (no internet?). Enter the code later on the License page.' -ForegroundColor Yellow
        }
    }
}

# 5. Network, power, shortcut ---------------------------------------------------
Write-Step 'Opening the firewall for the clinic network'
netsh advfirewall firewall delete rule name="DOXamigo" | Out-Null
netsh advfirewall firewall add rule name="DOXamigo" dir=in action=allow protocol=TCP localport=$Port profile=private,domain | Out-Null
powercfg /change standby-timeout-ac 0 | Out-Null
powercfg /change hibernate-timeout-ac 0 | Out-Null

$suffix = if ($Port -eq 80) { '' } else { ":$Port" }
$desktop = [Environment]::GetFolderPath('CommonDesktopDirectory')
[System.IO.File]::WriteAllText((Join-Path $desktop 'DOXamigo.url'), "[InternetShortcut]`r`nURL=http://localhost$suffix/admin`r`n", (New-Object System.Text.ASCIIEncoding))

# Optional remote support (key typed in, never stored in this installer).
if (-not $IsUpdate -and -not (Get-Command tailscale -ErrorAction SilentlyContinue)) {
    $tsKey = Read-Value 'Tailscale key for remote support (Enter to skip)'
    if ($tsKey) {
        $ts = Join-Path $env:TEMP 'tailscale-setup.exe'
        Invoke-WebRequest -Uri 'https://pkgs.tailscale.com/stable/tailscale-setup-latest.exe' -OutFile $ts -UseBasicParsing
        Start-Process -FilePath $ts -ArgumentList '/quiet' -Wait
        $tsExe = Join-Path $env:ProgramFiles 'Tailscale\tailscale.exe'
        if (Test-Path -LiteralPath $tsExe) { & $tsExe up "--authkey=$tsKey" --unattended | Out-Null }
    }
}

# 6. Done ----------------------------------------------------------------------
$lan = Get-LanAddress
Write-Host ''
Write-Host '=============================================' -ForegroundColor Green
Write-Host ' DOXamigo is ready' -ForegroundColor Green
Write-Host '=============================================' -ForegroundColor Green
if ($login) {
    Write-Host $login -ForegroundColor White
    Write-Host 'WRITE THE PASSWORD DOWN NOW - it is not shown again.' -ForegroundColor Yellow
}
Write-Host "On this PC:     http://localhost$suffix/admin  (desktop shortcut: DOXamigo)"
if ($lan) { Write-Host "Other devices:  http://$lan$suffix   (same Wi-Fi / network)" }
Write-Host 'Updates install automatically every night at 03:30.'
Write-Host 'Keep this PC on during clinic hours, and signed in to Windows (Docker starts after sign-in).'
Read-Host 'Press Enter to close'
