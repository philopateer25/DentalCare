<#
.SYNOPSIS
    Builds and pushes a new DOXamigo version for the clinics (Docker + Watchtower).

.DESCRIPTION
    One PRIVATE repository holds both images, told apart by tag:
        <image>:app-<version>  and  :app-<channel>    (PHP app, worker, scheduler)
        <image>:web-<version>  and  :web-<channel>    (nginx with the built frontend)

    Clinics on that channel pick it up the next night at 03:30 (Watchtower).
    Release to a couple of clinics first:  -Channel pilot   then later  -Channel stable.
    Roll back: run again on the previous commit (git checkout <old version>).

    Needs: docker (`docker login` with push rights), a clean git tree, and the
    repository set to PRIVATE on Docker Hub (the script cannot check that).

.EXAMPLE
    .\deploy\docker\publish.ps1 -Channel pilot
    .\deploy\docker\publish.ps1 -Channel stable
#>
[CmdletBinding()]
param(
    [ValidateSet('pilot', 'stable')]
    [string]$Channel = 'pilot',
    [string]$Image = 'philohany12/dentalcare-app',
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

    $app = "$Image`:app-$version"
    $web = "$Image`:web-$version"

    Write-Step "Building $app"
    Invoke-Docker @('build', '--target', 'production', '-t', $app, '-t', "$Image`:app-$Channel", '.')
    Write-Step "Building $web"
    Invoke-Docker @('build', '--target', 'web-server', '-t', $web, '-t', "$Image`:web-$Channel", '.')

    # Last check on the built image itself.
    $leak = & docker run --rm --entrypoint sh $app -c 'ls database/*.sqlite storage/app/backups/*.enc storage/app/license.enc storage/app/license.json .mcp.json 2>/dev/null; grep -rl "tskey-auth" --exclude-dir=vendor . 2>/dev/null'
    if ($leak) { throw "The image contains private files:`n$leak" }

    if ($NoPush) {
        Write-Host "Built (not pushed): $app, $web" -ForegroundColor Green
        return
    }

    Write-Host ''
    Write-Host "Pushing to $Image. It MUST be private on Docker Hub (Settings > Make private)." -ForegroundColor Yellow
    if ((Read-Host 'Is the repository private? (y/N)') -notmatch '^[Yy]') { throw 'Stopped: make the repository private first.' }

    Write-Step 'Pushing'
    foreach ($tag in @($app, "$Image`:app-$Channel", $web, "$Image`:web-$Channel")) {
        Invoke-Docker @('push', $tag)
    }
    Write-Host "Published $version to '$Channel'. Clinics on that channel update tonight at 03:30." -ForegroundColor Green
} finally {
    Pop-Location
}
