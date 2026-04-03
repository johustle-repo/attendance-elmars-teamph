[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

function Expand-ZipArchive {
    param(
        [Parameter(Mandatory = $true)] [string]$ZipPath,
        [Parameter(Mandatory = $true)] [string]$DestinationPath
    )
    # Use .NET ZipFile instead of Expand-Archive because PowerShell 5.1's
    # Expand-Archive silently skips files whose names start with '.' (e.g. .env.example).
    [System.IO.Compression.ZipFile]::ExtractToDirectory($ZipPath, $DestinationPath)
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$appZipPath = Join-Path $scriptRoot 'duscaff-attendance-portable.zip'
$phpZipPath = Join-Path $scriptRoot 'duscaff-attendance-php-runtime.zip'
$installDirectory = Join-Path $env:LOCALAPPDATA 'Programs\Duscaff Attendance'
$startMenuDirectory = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Duscaff Attendance'
$desktopShortcutPath = Join-Path ([Environment]::GetFolderPath('Desktop')) 'Duscaff Attendance.lnk'
$startMenuShortcutPath = Join-Path $startMenuDirectory 'Duscaff Attendance.lnk'
$payloadExtractDirectory = Join-Path $env:TEMP ('duscaff-attendance-install-' + [guid]::NewGuid().ToString('N'))
$launchScriptPath = Join-Path $installDirectory 'start-portable.bat'

function Write-Step {
    param([string]$Message)

    Write-Host "[Duscaff Installer] $Message"
}

function Copy-PayloadEntry {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceRoot,
        [Parameter(Mandatory = $true)]
        [string]$CurrentSource,
        [Parameter(Mandatory = $true)]
        [string]$DestinationRoot,
        [string[]]$SkipRelativePaths = @()
    )

    $relativePath = [System.IO.Path]::GetRelativePath($SourceRoot, $CurrentSource).Replace('\\', '/')

    foreach ($skipRelativePath in $SkipRelativePaths) {
        if ($relativePath -eq $skipRelativePath -or $relativePath.StartsWith($skipRelativePath + '/')) {
            return
        }
    }

    $sourceItem = Get-Item -LiteralPath $CurrentSource -Force
    $destinationPath = Join-Path $DestinationRoot ($relativePath -replace '/', '\\')

    if ($sourceItem.PSIsContainer) {
        New-Item -ItemType Directory -Force -Path $destinationPath | Out-Null

        foreach ($childItem in Get-ChildItem -LiteralPath $sourceItem.FullName -Force) {
            Copy-PayloadEntry -SourceRoot $SourceRoot -CurrentSource $childItem.FullName -DestinationRoot $DestinationRoot -SkipRelativePaths $SkipRelativePaths
        }

        return
    }

    $destinationParent = Split-Path -Parent $destinationPath
    if ($destinationParent) {
        New-Item -ItemType Directory -Force -Path $destinationParent | Out-Null
    }

    Copy-Item -LiteralPath $sourceItem.FullName -Destination $destinationPath -Force
}

function Copy-PayloadContents {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceRoot,
        [Parameter(Mandatory = $true)]
        [string]$DestinationRoot,
        [string[]]$SkipRelativePaths = @()
    )

    foreach ($item in Get-ChildItem -LiteralPath $SourceRoot -Force) {
        Copy-PayloadEntry -SourceRoot $SourceRoot -CurrentSource $item.FullName -DestinationRoot $DestinationRoot -SkipRelativePaths $SkipRelativePaths
    }
}

function New-Shortcut {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ShortcutPath,
        [Parameter(Mandatory = $true)]
        [string]$TargetPath,
        [Parameter(Mandatory = $true)]
        [string]$WorkingDirectory,
        [Parameter(Mandatory = $true)]
        [string]$Description
    )

    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($ShortcutPath)
    $shortcut.TargetPath = $TargetPath
    $shortcut.WorkingDirectory = $WorkingDirectory
    $shortcut.Description = $Description
    $shortcut.IconLocation = "$env:SystemRoot\System32\SHELL32.dll,220"
    $shortcut.Save()
}

try {
    if (-not (Test-Path -LiteralPath $appZipPath -PathType Leaf)) {
        throw 'The installer payload zip is missing.'
    }

    if (-not (Test-Path -LiteralPath $phpZipPath -PathType Leaf)) {
        throw 'The bundled PHP runtime zip is missing.'
    }

    Write-Step 'Extracting the app payload...'
    # Resolve to the long (non-8.3) path so that .NET extraction and PowerShell
    # Get-ChildItem / Get-Item always agree on the same path representation.
    $payloadExtractDirectory = (New-Item -ItemType Directory -Force -Path $payloadExtractDirectory).FullName
    Expand-ZipArchive -ZipPath $appZipPath -DestinationPath $payloadExtractDirectory

    $appPayloadRoot = Join-Path $payloadExtractDirectory 'duscaff-attendance-portable'
    if (-not (Test-Path -LiteralPath $appPayloadRoot -PathType Container)) {
        throw 'The extracted app payload folder is missing.'
    }

    $skipRelativePaths = @()
    if (Test-Path -LiteralPath $installDirectory -PathType Container) {
        Write-Step 'Updating an existing install and preserving local data...'
        $skipRelativePaths = @('.env', 'database/database.sqlite', 'storage')
    } else {
        Write-Step 'Creating the install directory...'
        New-Item -ItemType Directory -Force -Path $installDirectory | Out-Null
    }

    Write-Step 'Copying application files into the install directory...'
    Copy-PayloadContents -SourceRoot $appPayloadRoot -DestinationRoot $installDirectory -SkipRelativePaths $skipRelativePaths

    $installedPhpDirectory = Join-Path $installDirectory 'php'
    if (Test-Path -LiteralPath $installedPhpDirectory -PathType Container) {
        Write-Step 'Refreshing the bundled PHP runtime...'
        Remove-Item -LiteralPath $installedPhpDirectory -Recurse -Force
    }

    Write-Step 'Extracting the bundled PHP runtime...'
    Expand-ZipArchive -ZipPath $phpZipPath -DestinationPath $installDirectory

    if (-not (Test-Path -LiteralPath $launchScriptPath -PathType Leaf)) {
        throw 'The installed start script could not be found.'
    }

    Write-Step 'Creating desktop and Start menu shortcuts...'
    New-Item -ItemType Directory -Force -Path $startMenuDirectory | Out-Null
    New-Shortcut -ShortcutPath $desktopShortcutPath -TargetPath $launchScriptPath -WorkingDirectory $installDirectory -Description 'Start Duscaff Attendance'
    New-Shortcut -ShortcutPath $startMenuShortcutPath -TargetPath $launchScriptPath -WorkingDirectory $installDirectory -Description 'Start Duscaff Attendance'

    Write-Step 'Launching the installed application...'
    Start-Process -FilePath $launchScriptPath -WorkingDirectory $installDirectory | Out-Null
    exit 0
} catch {
    Write-Error $_.Exception.Message
    exit 1
} finally {
    if (Test-Path -LiteralPath $payloadExtractDirectory) {
        Remove-Item -LiteralPath $payloadExtractDirectory -Recurse -Force
    }
}