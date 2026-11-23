<#
.SYNOPSIS
    Makes DOXamigo-Docker-Setup.exe from deploy\install.ps1 with PS2EXE.

.DESCRIPTION
    install.ps1 is self-contained, so the Win-PS2EXE window works just as well.
    This script only adds the admin-rights request, product name and version.
    The .exe contains no secrets: the update token and Tailscale key are typed
    in at install time.

.EXAMPLE
    .\deploy\docker\build-installer.ps1
#>
[CmdletBinding()]
param([string]$Version = '1.0.0.0')

$ErrorActionPreference = 'Stop'
if (-not (Get-Module -ListAvailable ps2exe)) { throw 'PS2EXE is not installed: Install-Module ps2exe -Scope CurrentUser' }

$out = Join-Path $PSScriptRoot 'build'
New-Item -ItemType Directory -Force -Path $out | Out-Null
$exe = Join-Path $out 'DOXamigo-Docker-Setup.exe'

Import-Module ps2exe
Invoke-ps2exe -inputFile (Join-Path $PSScriptRoot '..\install.ps1') -outputFile $exe -requireAdmin -title 'DOXamigo Setup' -product 'DOXamigo' -company 'DOXamigo' -version $Version
Write-Host "Installer: $exe" -ForegroundColor Green
