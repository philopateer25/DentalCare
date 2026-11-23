<#
.SYNOPSIS
    Removes the DOXamigo Windows services and firewall rule. Keeps <root>\data
    (database, uploads, backups, .env) so a reinstall picks up where it left off.
    Called by the uninstaller; run elevated.
#>
[CmdletBinding()]
param([string]$Root = 'C:\DOXamigo')

$ErrorActionPreference = 'Continue'
$Services = Join-Path $Root 'services'

Get-Service -Name 'DOXamigo-*' -ErrorAction SilentlyContinue | ForEach-Object {
    $wrapper = Join-Path $Services "$($_.Name).exe"
    if ($_.Status -ne 'Stopped') { Stop-Service -Name $_.Name -Force -ErrorAction SilentlyContinue }
    if (Test-Path -LiteralPath $wrapper) {
        & $wrapper uninstall | Out-Null
    } else {
        sc.exe delete $_.Name | Out-Null
    }
}

netsh advfirewall firewall delete rule name="DOXamigo" | Out-Null

# The storage junction points into data\; remove the link only, never its target.
$appStorage = Join-Path $Root 'app\storage'
$item = Get-Item -LiteralPath $appStorage -ErrorAction SilentlyContinue
if ($item -and ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
    cmd /c rmdir "$appStorage" | Out-Null
}

Write-Host "DOXamigo services removed. Your data is still in $(Join-Path $Root 'data')."
