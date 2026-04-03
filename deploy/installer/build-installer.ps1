[CmdletBinding()]
param(
    [switch]$IncludeCurrentData,
    [string]$InstallerName = 'Duscaff-Attendance-Setup.exe',
    [string]$PortablePackageName = 'duscaff-attendance-portable',
    [string]$PhpRuntimeDir = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = (Resolve-Path (Join-Path $scriptRoot '..\..')).Path
$outputDirectory = Join-Path $projectRoot 'dist\installer'
$workDirectory = Join-Path $outputDirectory 'work'
$portableOutputOption = 'dist/installer/work'
$appPayloadZip = Join-Path $workDirectory ($PortablePackageName + '.zip')
$phpStageDirectory = Join-Path $workDirectory 'php-runtime-stage'
$phpRuntimePayloadRoot = Join-Path $phpStageDirectory 'php'
$phpPayloadZip = Join-Path $workDirectory 'duscaff-attendance-php-runtime.zip'
$installerPath = Join-Path $outputDirectory $InstallerName
$sedPath = Join-Path $workDirectory 'duscaff-attendance-installer.sed'
$installerSourceDirectory = $workDirectory.TrimEnd('\') + '\'

function Write-Step {
    param([string]$Message)

    Write-Host "[Installer Build] $Message"
}

function Invoke-ExternalCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FilePath,
        [string[]]$Arguments = @(),
        [string]$WorkingDirectory = $projectRoot
    )

    Push-Location $WorkingDirectory

    try {
        & $FilePath @Arguments
        $exitCode = $LASTEXITCODE
    } finally {
        Pop-Location
    }

    if ($exitCode -ne 0) {
        $argumentText = if ($Arguments.Count -eq 0) { '' } else { ' ' + ($Arguments -join ' ') }
        throw "Command failed with exit code ${exitCode}: $FilePath$argumentText"
    }
}

if ($env:OS -ne 'Windows_NT') {
    throw 'The Windows installer build only works on Windows.'
}

$phpCommand = (Get-Command php.exe -ErrorAction Stop | Select-Object -First 1 -ExpandProperty Source)

if ([string]::IsNullOrWhiteSpace($PhpRuntimeDir)) {
    $runtimeCandidates = New-Object System.Collections.Generic.List[string]

    foreach ($candidate in @(
        (Split-Path -Parent $phpCommand),
        'C:\php-8.5.2',
        'C:\xampp\php'
    )) {
        if (-not [string]::IsNullOrWhiteSpace($candidate) -and (Test-Path -LiteralPath $candidate -PathType Container)) {
            $runtimeCandidates.Add((Resolve-Path $candidate).Path)
        }
    }

    try {
        foreach ($candidatePhp in (& where.exe php.exe 2>$null)) {
            $candidateDirectory = Split-Path -Parent $candidatePhp
            if (Test-Path -LiteralPath $candidateDirectory -PathType Container) {
                $runtimeCandidates.Add((Resolve-Path $candidateDirectory).Path)
            }
        }
    } catch {
        # Fall back to the current PHP command directory when where.exe is unavailable.
    }

    $resolvedPhpRuntimeDir = $runtimeCandidates |
        Select-Object -Unique |
        Where-Object {
            (Test-Path -LiteralPath (Join-Path $_ 'php.exe') -PathType Leaf) -and
            (Test-Path -LiteralPath (Join-Path $_ 'php.ini') -PathType Leaf) -and
            (Test-Path -LiteralPath (Join-Path $_ 'ext') -PathType Container)
        } |
        Select-Object -First 1

    if (-not $resolvedPhpRuntimeDir) {
        $resolvedPhpRuntimeDir = Split-Path -Parent $phpCommand
    }
} else {
    $resolvedPhpRuntimeDir = (Resolve-Path $PhpRuntimeDir).Path
}

if (-not (Test-Path -LiteralPath $resolvedPhpRuntimeDir -PathType Container)) {
    throw 'The PHP runtime directory could not be found.'
}

$iexpressCommand = (Get-Command iexpress.exe -ErrorAction Stop | Select-Object -First 1 -ExpandProperty Source)

Write-Step 'Preparing installer output directories...'
if (Test-Path -LiteralPath $workDirectory) {
    Remove-Item -LiteralPath $workDirectory -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $workDirectory | Out-Null
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
if (Test-Path -LiteralPath $installerPath) {
    Remove-Item -LiteralPath $installerPath -Force
}

Write-Step 'Building production frontend assets...'
Invoke-ExternalCommand -FilePath 'cmd.exe' -Arguments @('/c', 'npm', 'run', 'build')

Write-Step 'Creating a fresh portable app payload...'
$portableArguments = @(
    'artisan',
    'app:package-portable',
    '--force',
    "--output=$portableOutputOption",
    "--name=$PortablePackageName"
)
if (-not $IncludeCurrentData) {
    $portableArguments += '--without-current-data'
}
Invoke-ExternalCommand -FilePath $phpCommand -Arguments $portableArguments -WorkingDirectory $projectRoot

if (-not (Test-Path -LiteralPath $appPayloadZip -PathType Leaf)) {
    throw 'The portable app zip payload was not created.'
}

Write-Step ('Bundling PHP runtime from ' + $resolvedPhpRuntimeDir + '...')
if (Test-Path -LiteralPath $phpStageDirectory) {
    Remove-Item -LiteralPath $phpStageDirectory -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $phpRuntimePayloadRoot | Out-Null
Get-ChildItem -LiteralPath $resolvedPhpRuntimeDir -Force | Copy-Item -Destination $phpRuntimePayloadRoot -Recurse -Force
Compress-Archive -Path $phpRuntimePayloadRoot -DestinationPath $phpPayloadZip -CompressionLevel Optimal -Force
Remove-Item -LiteralPath $phpStageDirectory -Recurse -Force

Write-Step 'Copying installer launch scripts...'
Copy-Item -LiteralPath (Join-Path $scriptRoot 'install-attendance.cmd') -Destination $workDirectory -Force
Copy-Item -LiteralPath (Join-Path $scriptRoot 'install-attendance.ps1') -Destination $workDirectory -Force

Write-Step 'Generating IExpress installer definition...'
$sedContent = @"
[Version]
Class=IEXPRESS
SEDVersion=3
[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=1
HideExtractAnimation=1
UseLongFileName=1
InsideCompressed=0
CAB_FixedSize=0
CAB_ResvCodeSigning=0
RebootMode=N
InstallPrompt=
DisplayLicense=
FinishMessage=Duscaff Attendance was installed. Use the desktop or Start menu shortcut to open it again.
TargetName=$installerPath
FriendlyName=Duscaff Attendance Setup
AppLaunched=install-attendance.cmd
PostInstallCmd=<None>
AdminQuietInstCmd=install-attendance.cmd
UserQuietInstCmd=install-attendance.cmd
SourceFiles=SourceFiles
[SourceFiles]
SourceFiles0=$installerSourceDirectory
[SourceFiles0]
install-attendance.cmd=
install-attendance.ps1=
$PortablePackageName.zip=
duscaff-attendance-php-runtime.zip=
"@
[System.IO.File]::WriteAllText($sedPath, $sedContent, $utf8NoBom)

Write-Step 'Building the Windows setup executable...'
$iexpressProcess = Start-Process -FilePath $iexpressCommand -ArgumentList @('/N', '/Q', '/M', $sedPath) -Wait -PassThru

for ($attempt = 0; $attempt -lt 30 -and -not (Test-Path -LiteralPath $installerPath -PathType Leaf); $attempt++) {
    Start-Sleep -Seconds 1
}

if (-not (Test-Path -LiteralPath $installerPath -PathType Leaf)) {
    throw "The setup executable was not created. IExpress exit code: $($iexpressProcess.ExitCode)"
}

Write-Step ('Installer ready at ' + $installerPath)