@echo off
setlocal

cd /d "%~dp0"

set "SHOULD_SEED=0"

set "PHP_EXE="
if exist "%~dp0php\php.exe" set "PHP_EXE=%~dp0php\php.exe"
if not defined PHP_EXE for %%I in (php.exe) do set "PHP_EXE=%%~$PATH:I"

if not defined PHP_EXE (
    echo PHP 8.2+ was not found.
    echo.
    echo Install PHP 8.2+ and add php.exe to PATH,
    echo or copy a portable PHP runtime into the "php" folder beside this script.
    pause
    exit /b 1
)

if not exist ".env" (
    if exist ".env.portable.example" (
        copy /Y ".env.portable.example" ".env" >nul
    ) else if exist ".env.example" (
        copy /Y ".env.example" ".env" >nul
    )
)

if not exist "database\database.sqlite" (
    type nul > "database\database.sqlite"
    set "SHOULD_SEED=1"
)

for %%I in ("database\database.sqlite") do (
    if "%%~zI"=="0" set "SHOULD_SEED=1"
)

"%PHP_EXE%" artisan optimize:clear
if errorlevel 1 (
    echo Failed to clear cached files.
    pause
    exit /b 1
)

set "INSTALL_ARGS=--force"
if "%SHOULD_SEED%"=="1" set "INSTALL_ARGS=--seed --force"

"%PHP_EXE%" artisan app:install %INSTALL_ARGS%
if errorlevel 1 (
    echo Portable setup failed.
    pause
    exit /b 1
)

if "%SHOULD_SEED%"=="1" (
    echo Seeded the default demo accounts for this portable install.
)

echo Portable setup complete.
exit /b 0