param()

$ErrorActionPreference = 'Stop'

$projectPath = Split-Path -Parent $PSScriptRoot

$candidateDirectories = @(
    'C:\php-8.5.2',
    'C:\xampp\php'
)

$currentPhp = Get-Command php.exe -ErrorAction SilentlyContinue
if ($currentPhp) {
    $candidateDirectories += Split-Path -Parent $currentPhp.Source
}

$whereResults = & where.exe php 2>$null
foreach ($result in $whereResults) {
    if ([string]::IsNullOrWhiteSpace($result)) {
        continue
    }

    $candidateDirectories += Split-Path -Parent $result
}

$phpDirectory = $candidateDirectories |
    Where-Object { $_ } |
    Select-Object -Unique |
    Where-Object {
        (Test-Path (Join-Path $_ 'php.exe')) -and
        (Test-Path (Join-Path $_ 'php.ini')) -and
        (Test-Path (Join-Path $_ 'ext'))
    } |
    Select-Object -First 1

if (-not $phpDirectory) {
    throw 'Unable to find a full PHP runtime. Install PHP or update scripts/dev.ps1 with the correct php.exe path.'
}

$phpExe = Join-Path $phpDirectory 'php.exe'

Write-Host "[dev] Using PHP: $phpExe"

$serverCommand = '"' + $phpExe + '" artisan serve --host=127.0.0.1 --port=8000'
$viteCommand = 'npm run dev -- --host 127.0.0.1'

Push-Location $projectPath

try {
    & npx concurrently `
        -c '#93c5fd,#fdba74' `
        --kill-others-on-fail `
        $serverCommand `
        $viteCommand `
        --names 'server,vite'

    exit $LASTEXITCODE
} finally {
    Pop-Location
}
