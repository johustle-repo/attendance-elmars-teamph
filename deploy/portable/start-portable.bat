@echo off
setlocal

cd /d "%~dp0"
call "%~dp0prepare-portable.bat"
if errorlevel 1 exit /b %errorlevel%

set "PHP_EXE="
if exist "%~dp0php\php.exe" set "PHP_EXE=%~dp0php\php.exe"
if not defined PHP_EXE for %%I in (php.exe) do set "PHP_EXE=%%~$PATH:I"

if not defined PHP_EXE (
    echo PHP 8.2+ was not found.
    pause
    exit /b 1
)

echo Starting Laravel on http://127.0.0.1:8000
start "" http://127.0.0.1:8000
"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000
