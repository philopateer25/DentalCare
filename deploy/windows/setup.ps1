<#
.SYNOPSIS
    DOXamigo native Windows install / update (Phase 6). Run elevated.

.DESCRIPTION
    Called by DOXamigo-Setup.exe after it copied the files, and safe to run again
    (an update runs the same script). Layout under -Root (default C:\DOXamigo):

        app\        the application (replaced by every update)
        app\storage -> data\storage  (junction, so uploads/backups survive updates)
        runtime\    php, caddy, winsw, vc_redist
        services\   one WinSW wrapper (.exe + .xml) per Windows service
        data\       .env, database.sqlite, storage, logs  (never touched by updates)

    Windows PowerShell 5.1 compatible (built into Windows 10).

.PARAMETER ParamsFile
    JSON written by the installer: clinicName, adminEmail, enrollmentCode, centralUrl.
    Deleted after reading.
#>
[CmdletBinding()]
param(
    [string]$Root = 'C:\DOXamigo',
    [string]$ParamsFile = '',
    [int]$Port = 80,
    [int]$PhpWorkers = 4
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

$App      = Join-Path $Root 'app'
$Runtime  = Join-Path $Root 'runtime'
$Data     = Join-Path $Root 'data'
$Services = Join-Path $Root 'services'
$Logs     = Join-Path $Data 'logs'
$Php      = Join-Path $Runtime 'php\php.exe'
$PhpCgi   = Join-Path $Runtime 'php\php-cgi.exe'
$PhpIni   = Join-Path $Runtime 'php\php.ini'
$Caddy    = Join-Path $Runtime 'caddy\caddy.exe'
$WinSW    = Join-Path $Runtime 'winsw\WinSW-x64.exe'
$EnvFile  = Join-Path $Data '.env'
$DbFile   = Join-Path $Data 'database.sqlite'
$SetupLog = Join-Path $Logs 'setup.log'

New-Item -ItemType Directory -Force -Path $Data, $Logs, $Services | Out-Null
Start-Transcript -Path $SetupLog -Append | Out-Null

function Write-Step([string]$text) { Write-Host "==> $text" -ForegroundColor Cyan }

function Assert-Admin {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Run this as Administrator.'
    }
}

function Invoke-Php([string[]]$Arguments, [switch]$AllowFailure) {
    Push-Location $App
    try {
        # Out-Host: PHP's output goes to the log, not into this function's return value.
        # No 2>&1: in PowerShell 5.1 a PHP warning on stderr would become a terminating error here.
        & $Php -c $PhpIni @Arguments | Out-Host
        $code = $LASTEXITCODE
    } finally {
        Pop-Location
    }
    if ($code -ne 0 -and -not $AllowFailure) {
        throw "php $($Arguments -join ' ') failed (exit $code). See $SetupLog."
    }
    return $code
}

function Get-LanAddress {
    # First private IPv4 on an "Up" adapter with a default gateway (the clinic network).
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
    } catch {
        return $false
    } finally {
        if ($listener) { $listener.Stop() }
    }
}

function New-RandomBase64([int]$bytes) {
    $buffer = New-Object byte[] $bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return [Convert]::ToBase64String($buffer)
}

function Set-EnvValue([string]$path, [string]$key, [string]$value) {
    $lines = Get-Content -LiteralPath $path -Encoding UTF8
    $found = $false
    $lines = foreach ($line in $lines) {
        if ($line -match "^$([regex]::Escape($key))=") { $found = $true; "$key=$value" } else { $line }
    }
    if (-not $found) { $lines += "$key=$value" }
    # UTF-8 without BOM (a BOM breaks the first .env line).
    [System.IO.File]::WriteAllLines($path, [string[]]$lines, (New-Object System.Text.UTF8Encoding($false)))
}

function Install-WinSWService([string]$id, [string]$displayName, [string]$executable, [string]$arguments, [hashtable]$envVars) {
    $exe = Join-Path $Services "$id.exe"
    $xml = Join-Path $Services "$id.xml"
    Copy-Item -LiteralPath $WinSW -Destination $exe -Force

    $envXml = ''
    if ($envVars) {
        foreach ($k in $envVars.Keys) { $envXml += "  <env name=`"$k`" value=`"$($envVars[$k])`" />`r`n" }
    }
    $content = @"
<service>
  <id>$id</id>
  <name>$displayName</name>
  <description>DOXamigo clinic system ($displayName). Installed by DOXamigo-Setup.</description>
  <executable>$executable</executable>
  <arguments>$arguments</arguments>
  <workingdirectory>$App</workingdirectory>
$envXml  <startmode>Automatic</startmode>
  <onfailure action="restart" delay="5 sec" />
  <onfailure action="restart" delay="30 sec" />
  <resetfailure>1 hour</resetfailure>
  <stoptimeout>15 sec</stoptimeout>
  <logpath>$Logs</logpath>
  <log mode="roll-by-size"><sizeThreshold>10240</sizeThreshold><keepFiles>5</keepFiles></log>
</service>
"@
    [System.IO.File]::WriteAllText($xml, $content, (New-Object System.Text.UTF8Encoding($false)))

    $existing = Get-Service -Name $id -ErrorAction SilentlyContinue
    if ($existing) {
        if ($existing.Status -ne 'Stopped') { & $exe stop | Out-Null }
        & $exe refresh | Out-Null
    } else {
        & $exe install | Out-Null
    }
}

# -----------------------------------------------------------------------------
Assert-Admin

$params = @{ clinicName = ''; adminEmail = ''; enrollmentCode = ''; centralUrl = '' }
if ($ParamsFile -and (Test-Path -LiteralPath $ParamsFile)) {
    $json = Get-Content -LiteralPath $ParamsFile -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($k in @('clinicName', 'adminEmail', 'enrollmentCode', 'centralUrl')) {
        if ($json.PSObject.Properties[$k]) { $params[$k] = [string]$json.$k }
    }
    Remove-Item -LiteralPath $ParamsFile -Force
}
$isUpgrade = Test-Path -LiteralPath $EnvFile

Write-Step "DOXamigo setup ($(if ($isUpgrade) { 'update' } else { 'new install' })) in $Root"

# 1. Visual C++ runtime (PHP needs it) -----------------------------------------
Write-Step 'Checking PHP'
& $Php -n -v *> $null
if ($LASTEXITCODE -ne 0) {
    $redist = Join-Path $Runtime 'vc_redist.x64.exe'
    if (-not (Test-Path -LiteralPath $redist)) { throw 'PHP cannot start and vc_redist.x64.exe is missing.' }
    Write-Step 'Installing the Microsoft Visual C++ runtime'
    Start-Process -FilePath $redist -ArgumentList '/install', '/quiet', '/norestart' -Wait
    & $Php -n -v *> $null
    if ($LASTEXITCODE -ne 0) { throw 'PHP still cannot start after installing the Visual C++ runtime.' }
}

$required = @('bcmath', 'curl', 'exif', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sodium', 'sqlite3', 'zip', 'Zend OPcache')
$loaded = & $Php -c $PhpIni -m
$missing = $required | Where-Object { $loaded -notcontains $_ }
if ($missing) { throw "PHP extensions missing: $($missing -join ', ')" }

# 2. Stop running services before touching files (update) ----------------------
Get-Service -Name 'DOXamigo-*' -ErrorAction SilentlyContinue | Where-Object { $_.Status -ne 'Stopped' } | ForEach-Object {
    Write-Step "Stopping $($_.Name)"
    Stop-Service -Name $_.Name -Force -ErrorAction SilentlyContinue
}

# 3. Data folder + storage junction --------------------------------------------
Write-Step 'Preparing the data folder'
$dataStorage = Join-Path $Data 'storage'
$appStorage = Join-Path $App 'storage'
$appStorageItem = Get-Item -LiteralPath $appStorage -ErrorAction SilentlyContinue
if ($appStorageItem -and -not ($appStorageItem.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
    if (-not (Test-Path -LiteralPath $dataStorage)) {
        Move-Item -LiteralPath $appStorage -Destination $dataStorage
    } else {
        Remove-Item -LiteralPath $appStorage -Recurse -Force
    }
}
foreach ($sub in @('app\public', 'app\private', 'framework\cache\data', 'framework\sessions', 'framework\views', 'logs')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $dataStorage $sub) | Out-Null
}
if (-not (Test-Path -LiteralPath $appStorage)) {
    cmd /c mklink /J "$appStorage" "$dataStorage" | Out-Null
}

# 4. Port + addresses ----------------------------------------------------------
if ($isUpgrade) {
    # Keep the port chosen at first install (it may have fallen back to 8080).
    $saved = Select-String -LiteralPath $EnvFile -Pattern '^DOXAMIGO_PORT=(\d+)' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($saved) { $Port = [int]$saved.Matches[0].Groups[1].Value }
} elseif (-not (Test-PortFree $Port)) {
    Write-Warning "Port $Port is already used by another program (often IIS or Skype). Using 8080."
    $Port = 8080
}
$lan = Get-LanAddress
$portSuffix = if ($Port -eq 80) { '' } else { ":$Port" }
$lanUrl = if ($lan) { "http://$lan$portSuffix" } else { '' }

# 5. .env (written once, then kept) --------------------------------------------
if (-not $isUpgrade) {
    Write-Step 'Writing settings'
    $template = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'env.template') -Raw -Encoding UTF8
    $central = if ($params.centralUrl) { $params.centralUrl } else { 'https://central.doxamigo.com' }
    $values = @{
        '{{APP_KEY}}'         = 'base64:' + (New-RandomBase64 32)
        '{{APP_URL}}'         = $(if ($lanUrl) { $lanUrl } else { "http://localhost$portSuffix" })
        '{{LAN_URL}}'         = $lanUrl
        '{{DB_PATH}}'         = $DbFile
        '{{CENTRAL_URL}}'     = $central
        '{{BACKUP_PASSWORD}}' = (New-RandomBase64 18)
    }
    foreach ($k in $values.Keys) { $template = $template.Replace($k, $values[$k]) }
    $template += "`r`nDOXAMIGO_PORT=$Port`r`n"
    [System.IO.File]::WriteAllText($EnvFile, $template, (New-Object System.Text.UTF8Encoding($false)))
} elseif ($lanUrl) {
    # The PC may have a new address after a router change.
    Set-EnvValue $EnvFile 'CLINIC_LAN_URL' $lanUrl
}
Copy-Item -LiteralPath $EnvFile -Destination (Join-Path $App '.env') -Force

# 6. Database + first clinic ---------------------------------------------------
if (-not (Test-Path -LiteralPath $DbFile)) { New-Item -ItemType File -Path $DbFile | Out-Null }
Write-Step 'Updating the database'
Invoke-Php @('artisan', 'migrate', '--force') | Out-Null

if (-not $isUpgrade -and $params.clinicName -and $params.adminEmail) {
    Write-Step 'Creating the clinic and its first admin'
    Invoke-Php @('artisan', 'clinic:install', "--clinic=$($params.clinicName)", "--admin-email=$($params.adminEmail)", "--credentials-file=$(Join-Path $Data 'first-login.txt')") | Out-Null
}

Invoke-Php @('artisan', 'storage:link', '--force') -AllowFailure | Out-Null

Write-Step 'Optimising'
Invoke-Php @('artisan', 'optimize:clear') -AllowFailure | Out-Null
Invoke-Php @('artisan', 'optimize') | Out-Null
Invoke-Php @('artisan', 'filament:cache-components') -AllowFailure | Out-Null
Invoke-Php @('artisan', 'icons:cache') -AllowFailure | Out-Null

# 7. Web server config ---------------------------------------------------------
$upstreams = (1..$PhpWorkers | ForEach-Object { "127.0.0.1:$(9000 + $_)" }) -join ' '
$publicDir = (Join-Path $App 'public').Replace('\', '/')
$caddyfile = @"
{
	admin off
	auto_https off
	persist_config off
}

:$Port {
	root * $publicDir
	encode gzip
	request_body {
		max_size 55MB
	}
	php_fastcgi $upstreams {
		lb_policy least_conn
		dial_timeout 5s
		read_timeout 180s
	}
	file_server
	log {
		output file $($Logs.Replace('\', '/'))/web-access.log {
			roll_size 10MiB
			roll_keep 5
		}
	}
}
"@
$caddyConfig = Join-Path $Runtime 'caddy\Caddyfile'
[System.IO.File]::WriteAllText($caddyConfig, $caddyfile, (New-Object System.Text.UTF8Encoding($false)))

# 8. Windows services ----------------------------------------------------------
Write-Step 'Installing Windows services'
for ($i = 1; $i -le $PhpWorkers; $i++) {
    Install-WinSWService "DOXamigo-PHP-$i" "DOXamigo PHP worker $i" $PhpCgi "-b 127.0.0.1:$(9000 + $i) -c `"$PhpIni`"" @{ PHP_FCGI_MAX_REQUESTS = '0' }
}
# Remove workers left over from an install that had more of them.
Get-Service -Name 'DOXamigo-PHP-*' -ErrorAction SilentlyContinue | Where-Object { [int]($_.Name -replace '\D', '') -gt $PhpWorkers } | ForEach-Object {
    & (Join-Path $Services "$($_.Name).exe") uninstall | Out-Null
}
Install-WinSWService 'DOXamigo-Web' 'DOXamigo web server' $Caddy "run --config `"$caddyConfig`" --adapter caddyfile" $null
Install-WinSWService 'DOXamigo-Queue' 'DOXamigo background jobs' $Php "-c `"$PhpIni`" artisan queue:work database --sleep=3 --tries=3" $null
# schedule:work = heartbeat every 30 min, license clock, nightly backup at 02:00.
Install-WinSWService 'DOXamigo-Scheduler' 'DOXamigo scheduler' $Php "-c `"$PhpIni`" artisan schedule:work" $null

Get-Service -Name 'DOXamigo-*' | ForEach-Object { Start-Service -Name $_.Name }

# 9. Firewall (clinic network only) + power ------------------------------------
Write-Step 'Opening the firewall for the clinic network'
netsh advfirewall firewall delete rule name="DOXamigo" | Out-Null
netsh advfirewall firewall add rule name="DOXamigo" dir=in action=allow protocol=TCP localport=$Port profile=private,domain | Out-Null

Write-Step 'Keeping this PC awake while plugged in'
powercfg /change standby-timeout-ac 0 | Out-Null
powercfg /change hibernate-timeout-ac 0 | Out-Null

# 10. License ------------------------------------------------------------------
if ($params.enrollmentCode) {
    Write-Step 'Activating the license'
    $code = Invoke-Php @('artisan', 'license:activate', $params.enrollmentCode.Trim()) -AllowFailure
    if ($code -ne 0) { Write-Warning 'Activation failed (no internet?). Enter the code later on the License page.' }
}

# 11. Desktop shortcut (all users) ---------------------------------------------
$publicDesktop = [Environment]::GetFolderPath('CommonDesktopDirectory')
if ($publicDesktop) {
    $shortcut = "[InternetShortcut]`r`nURL=http://localhost$portSuffix/admin`r`n"
    [System.IO.File]::WriteAllText((Join-Path $publicDesktop 'DOXamigo.url'), $shortcut, (New-Object System.Text.ASCIIEncoding))
}

# 12. Summary for the installer's last page -----------------------------------
$summary = @("Open DOXamigo:", "  On this PC:       http://localhost$portSuffix")
if ($lanUrl) { $summary += "  Other devices:    $lanUrl   (same Wi-Fi / network)" } else { $summary += '  Other devices:    no network address found - connect this PC to the clinic network.' }
$summary += "Keep this PC switched on during clinic hours."
[System.IO.File]::WriteAllLines((Join-Path $Data 'addresses.txt'), [string[]]$summary, (New-Object System.Text.UTF8Encoding($false)))

Write-Step 'Done'
$summary | ForEach-Object { Write-Host $_ }
Stop-Transcript | Out-Null
