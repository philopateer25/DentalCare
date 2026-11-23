<#
.SYNOPSIS
    Builds DOXamigo-Setup-<version>.exe (Phase 6) on a developer PC.

.DESCRIPTION
    1. Downloads the pinned runtime (versions.json) into build\cache and checks SHA-256.
    2. Stages the app from git (HEAD, no uncommitted changes), installs production
       Composer packages and the built frontend (public\build).
    3. Compiles DOXamigo.iss with Inno Setup 6 (ISCC.exe) if installed.

    Needs: git, composer, node/npm, PHP 8.3+ on PATH; Inno Setup 6 for the .exe.

.EXAMPLE
    .\deploy\windows\build.ps1 -CentralUrl https://central.doxamigo.com
    .\deploy\windows\build.ps1 -PinHashes     # first run: record the downloaded files' SHA-256
#>
[CmdletBinding()]
param(
    [string]$CentralUrl = 'https://central.doxamigo.com',
    [string]$Version = '',
    [switch]$PinHashes,
    [switch]$SkipFrontend,
    [switch]$AllowNoProductionKey
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

$Here     = $PSScriptRoot
$Repo     = (Resolve-Path (Join-Path $Here '..\..')).Path
$Build    = Join-Path $Here 'build'
$Cache    = Join-Path $Build 'cache'
$Stage    = Join-Path $Build 'stage'
$Versions = Join-Path $Here 'versions.json'

function Write-Step([string]$text) { Write-Host "==> $text" -ForegroundColor Cyan }

function Invoke-Native([string]$exe, [string[]]$arguments, [string]$workDir = $Repo) {
    Push-Location $workDir
    try {
        & $exe @arguments
        if ($LASTEXITCODE -ne 0) { throw "$exe $($arguments -join ' ') failed (exit $LASTEXITCODE)" }
    } finally {
        Pop-Location
    }
}

# 0. Safety checks -------------------------------------------------------------
Write-Step 'Checking the repository'
Push-Location $Repo
try {
    $dirty = git status --porcelain --untracked-files=no
    if ($dirty) { throw 'Commit or stash your changes first: the installer is built from HEAD.' }
    if (-not $Version) { $Version = (git describe --tags --always --dirty).Trim() }
} finally {
    Pop-Location
}

# Clinic installs run APP_ENV=production, where only the public keys written in
# config/license.php are trusted (never .env). Without one, no clinic can activate.
$licenseConfig = Get-Content -LiteralPath (Join-Path $Repo 'config\license.php') -Raw
if ($licenseConfig -notmatch "(?m)^\s*'k\d+'\s*=>\s*'[A-Za-z0-9+/=]{40,}'") {
    if (-not $AllowNoProductionKey) {
        throw "config/license.php has no production public key. Generate the central app's key (npm run keygen in doxamigo-central), put the PUBLIC half in 'public_keys', commit, and build again. (-AllowNoProductionKey builds a test installer anyway.)"
    }
    Write-Warning 'Building WITHOUT a production public key: clinics will not be able to activate.'
}

# 1. Runtime downloads ---------------------------------------------------------
New-Item -ItemType Directory -Force -Path $Cache | Out-Null
$pins = Get-Content -LiteralPath $Versions -Raw | ConvertFrom-Json
$changed = $false

function Get-Pinned([string]$name) {
    $entry = $pins.$name
    $file = Join-Path $Cache ([IO.Path]::GetFileName(([Uri]$entry.url).AbsolutePath))
    if (-not (Test-Path -LiteralPath $file)) {
        Write-Step "Downloading $name $($entry.version)"
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $entry.url -OutFile $file -UseBasicParsing
    }
    $hash = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant()
    if (-not $entry.sha256) {
        if (-not $PinHashes) { throw "$name has no pinned SHA-256 yet. Run once with -PinHashes, check the files, then commit versions.json." }
        $entry.sha256 = $hash
        $script:changed = $true
        Write-Host "    pinned $name = $hash"
    } elseif ($entry.sha256 -ne $hash) {
        throw "SHA-256 mismatch for $name ($file). Expected $($entry.sha256), got $hash. Delete the file and check the source."
    }
    return $file
}

$phpZip   = Get-Pinned 'php'
$caddyZip = Get-Pinned 'caddy'
$winsw    = Get-Pinned 'winsw'
$vcredist = Get-Pinned 'vcredist'
if ($changed) {
    $pins | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $Versions -Encoding UTF8
    Write-Warning 'versions.json updated with new hashes - review and commit it.'
}

# 2. Stage ---------------------------------------------------------------------
Write-Step 'Staging files'
if (Test-Path -LiteralPath $Stage) { Remove-Item -LiteralPath $Stage -Recurse -Force }
$stageApp = Join-Path $Stage 'app'
$stageRuntime = Join-Path $Stage 'runtime'
New-Item -ItemType Directory -Force -Path $stageApp, (Join-Path $stageRuntime 'php'), (Join-Path $stageRuntime 'caddy'), (Join-Path $stageRuntime 'winsw'), (Join-Path $Stage 'setup') | Out-Null

Expand-Archive -LiteralPath $phpZip -DestinationPath (Join-Path $stageRuntime 'php') -Force
Copy-Item -LiteralPath (Join-Path $Here 'runtime\php.ini') -Destination (Join-Path $stageRuntime 'php\php.ini') -Force
Expand-Archive -LiteralPath $caddyZip -DestinationPath (Join-Path $stageRuntime 'caddy') -Force
Copy-Item -LiteralPath $winsw -Destination (Join-Path $stageRuntime 'winsw\WinSW-x64.exe') -Force
Copy-Item -LiteralPath $vcredist -Destination (Join-Path $stageRuntime 'vc_redist.x64.exe') -Force

foreach ($f in @('setup.ps1', 'uninstall.ps1', 'runtime\env.template')) {
    Copy-Item -LiteralPath (Join-Path $Here $f) -Destination (Join-Path $Stage 'setup') -Force
}

# The app, exactly as committed.
$archive = Join-Path $Build 'app.zip'
Invoke-Native 'git' @('archive', '--format=zip', '-o', $archive, 'HEAD')
Expand-Archive -LiteralPath $archive -DestinationPath $stageApp -Force
Remove-Item -LiteralPath $archive -Force

Write-Step 'Installing production PHP packages'
Invoke-Native 'composer' @('install', '--no-dev', '--classmap-authoritative', '--no-interaction', '--no-progress', '--no-scripts') $stageApp
Invoke-Native 'php' @('artisan', 'package:discover', '--ansi') $stageApp

if (-not $SkipFrontend) {
    Write-Step 'Building the frontend'
    Invoke-Native 'npm' @('ci') $Repo
    Invoke-Native 'npm' @('run', 'build') $Repo
}
Copy-Item -LiteralPath (Join-Path $Repo 'public\build') -Destination (Join-Path $stageApp 'public') -Recurse -Force

# Not needed on a clinic PC.
foreach ($drop in @('tests', 'scratch', 'testing_scripts', 'docker', 'deploy', '.github', 'node_modules', 'design', '.env.example', 'phpunit.xml', 'docker-compose.yml', 'Dockerfile')) {
    $p = Join-Path $stageApp $drop
    if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force }
}
Set-Content -LiteralPath (Join-Path $stageApp 'VERSION') -Value $Version -Encoding ASCII

# 3. Installer -----------------------------------------------------------------
$iscc = @(
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1

if (-not $iscc) {
    Write-Warning "Inno Setup 6 not found. Files are staged in $Stage - install Inno Setup (https://jrsoftware.org/isdl.php) and run this again to get the .exe."
    return
}

Write-Step 'Compiling the installer'
Invoke-Native $iscc @("/DAppVersion=$Version", "/DCentralUrl=$CentralUrl", "/DStageDir=$Stage", "/O$Build", (Join-Path $Here 'DOXamigo.iss')) $Here
Write-Host "Installer: $(Join-Path $Build "DOXamigo-Setup-$Version.exe")" -ForegroundColor Green
