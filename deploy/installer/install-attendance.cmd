@echo off
setlocal

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-attendance.ps1"
if errorlevel 1 (
    echo Installation failed.
    pause
    exit /b 1
)

exit /b 0