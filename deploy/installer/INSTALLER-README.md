# Windows EXE Installer

Use this workflow when you want a single Windows setup `.exe` instead of the portable app folder.

## Build the installer

From the project root:

```powershell
composer run package:installer
```

That command:

- builds the Vite production frontend assets
- creates a fresh portable app payload with no copied SQLite data
- bundles the current local PHP runtime into the installer
- generates `dist/installer/Duscaff-Attendance-Setup.exe`

## Optional: include the current SQLite data

If you want the installer to ship with the current `database/database.sqlite` contents, run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File deploy/installer/build-installer.ps1 -IncludeCurrentData
```

## Install behavior on the target computer

When the `.exe` runs on another Windows computer, it:

- installs into `%LOCALAPPDATA%\Programs\Duscaff Attendance`
- creates desktop and Start menu shortcuts
- launches the installed app after setup finishes
- preserves `.env`, `database/database.sqlite`, and `storage` on reinstall so local data is not overwritten