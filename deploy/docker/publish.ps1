<#
.SYNOPSIS
    Builds and pushes a new DOXamigo version for the clinics (Docker + Watchtower).

.DESCRIPTION
    Builds two images from the current commit and pushes them to the registry:
        <app image>:<version>  and  :<channel>     (PHP app, worker, scheduler)
        <web image>:<version>  and  :<channel>     (nginx with the built frontend)

    Clinics on that channel pick it up the next night at 03:30 (Watchtower).
    Release to a couple of clinics first:  -Channel pilot   then later  -Channel stable.
    Roll back: run again on the previous commit, or re-tag an older <version> as the channel.

    Needs: docker (logged in with push rights: `docker login`), a clean git tree.

.EXAMPLE
    .\deploy\docker\publish.ps1 -Channel pilot
    .\deploy\docker\publish.ps1 -Channel stable
#>
[CmdletBinding()]
param(
    [ValidateSet('pilot', 'stable')]
    [string]$Channel = 'pilot',
    [string]$AppImage = 'philohany12/dentalcare-app',
    [string]$WebImage = 'philohany12/dentalcare-web',
    [switch]$NoPush,
    [switch]$AllowNoProductionKey
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2
$Repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

function Write-Step([string]$text) { Write-Host "==> $text" -ForegroundColor Cyan }
function Invoke-Docker([string[]]$arguments) {
    & docker @arguments
    if ($LASTEXITCODE -ne 0) { throw "docker $($arguments -join ' ') failed (exit $LASTEXITCODE)" }
}

Push-Location $Repo
try {
    if (git status --porcelain --untracked-files=no) { throw 'Commit or stash your changes first: images are built from the committed code.' }
    $version = (git describe --tags --always).Trim()

    # Clinics run APP_ENV=production: only keys written in config/license.php are trusted.
    $licenseConfig = Get-Content -LiteralPath 'config\license.php' -Raw
    if ($licenseConfig -notmatch "(?m)^\s*'k\d+'\s*=>\s*'[A-Za-z0-9+/=]{40,}'" -and -not $AllowNoProductionKey) {
        throw 'config/license.php has no production public key; clinics could not activate.'
    }

    # The image must never carry the developer database, backups or keys.
    foreach ($must in @('/database/*.sqlite', '/storage/app/*', '.mcp.json', '/.claude/')) {
        if (-not (Select-String -LiteralPath '.dockerignore' -SimpleMatch -Pattern $must -Quiet)) {
            throw ".dockerignore must exclude $must"
        }
    }

    Write-Step "Building $AppImage`:$version"
    Invoke-Docker @('build', '--target', 'production', '-t', "$AppImage`:$version", '-t', "$AppImage`:$Channel", '.')
    Write-Step "Building $WebImage`:$version"
    Invoke-Docker @('build', '--target', 'web-server', '-t', "$WebImage`:$version", '-t', "$WebImage`:$Channel", '.')

    # Last check on the built image itself.
    $leak = & docker run --rm --entrypoint sh "$AppImage`:$version" -c 'ls database/*.sqlite storage/app/backups/*.enc storage/app/license.enc .mcp.json 2>/dev/null'
    if ($leak) { throw "The image contains private files:`n$leak" }

    if ($NoPush) {
        Write-Host "Built (not pushed): $AppImage`:$version, $WebImage`:$version" -ForegroundColor Green
        return
    }

    Write-Step 'Pushing'
    foreach ($tag in @("$AppImage`:$version", "$AppImage`:$Channel", "$WebImage`:$version", "$WebImage`:$Channel")) {
        Invoke-Docker @('push', $tag)
    }
    Write-Host "Published $version to '$Channel'. Clinics on that channel update tonight at 03:30." -ForegroundColor Green
} finally {
    Pop-Location
}
