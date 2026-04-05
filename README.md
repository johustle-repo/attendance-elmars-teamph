# Elmar's Team PH Attendance Backup

Laravel 12 + Inertia React attendance management app with:

- QR-based attendance scanning
- admin and member management
- attendance dashboard and summaries
- monthly backup export in JSON, Excel, and PDF
- optional Firebase sync support

## Features

- attendance logging with time-in and time-out
- user status and role management
- admin backup center
- printable/downloadable PDF attendance backup
- Excel and JSON export
- seeded demo accounts for local testing

## Tech Stack

- Laravel 12
- PHP 8.2+
- Inertia.js
- React
- TypeScript
- Vite
- Tailwind CSS

## Requirements

Install these first on the computer where you will clone the project:

- Git
- PHP `8.2` or newer
- Composer
- Node.js and npm
- SQLite or MySQL

Recommended for Windows local development:

- XAMPP if you want MySQL and Apache
- Git Bash or PowerShell

Typical PHP extensions needed by Laravel:

- `openssl`
- `pdo`
- `mbstring`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `fileinfo`

## Clone The Project

```bash
git clone https://github.com/johustle-repo/attendance-elmars-teamph.git
cd attendance-elmars-teamph
```

## Complete Installation

This section is the full setup flow for a new machine.

### 1. Choose your database

You can use either SQLite or MySQL.

#### Option A: SQLite

This is the fastest and easiest local setup.

The installer will create `database/database.sqlite` automatically, and the default `.env.example` is already configured for SQLite.

#### Option B: MySQL / XAMPP

If you are using XAMPP on another computer:

1. Start `Apache` and `MySQL` from the XAMPP Control Panel.
2. Create a database in phpMyAdmin named `attendance_elmars_teamph`.
3. Update `.env` before running the installer.

SQL example:

```sql
CREATE DATABASE attendance_elmars_teamph;
```

MySQL `.env` values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_elmars_teamph
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Install PHP and frontend dependencies

```bash
composer install
npm install
```

### 3. Run the installer

```bash
php artisan app:install --seed
```

The installer will:

- create `.env` from `.env.example` when needed
- create `database/database.sqlite` automatically when you use SQLite
- generate the Laravel app key
- create the public storage link when possible
- run database migrations
- seed the default admin and member accounts

### 4. Build or run frontend assets

For development:

```bash
npm run dev
```

For a production-style local build:

```bash
npm run build
```

### 5. Start the Laravel app

In a separate terminal:

```bash
php artisan serve
```

Then open:

```text
http://127.0.0.1:8000
```

## Quick Install Shortcut

If you want the shortest setup path from a fresh clone:

```bash
composer run setup
```

Then run:

```bash
php artisan serve
npm run dev
```

Note:

- `composer run setup` installs dependencies, prepares `.env`, creates the SQLite database if needed, runs migrations, seeds demo users, and builds the frontend assets
- if you want MySQL instead of SQLite, edit `.env` before running `php artisan app:install --seed`

## Development Mode

You can also run the app with the combined development command:

```bash
composer run dev
```

That starts:

- Laravel server
- Vite dev server

## Seeded Accounts

After running `php artisan app:install --seed`, `php artisan migrate --seed`, or `php artisan db:seed`, these demo accounts are available:

- Super Admin: `superadmin@duscaff.local`
- Admin: `admin@duscaff.local`
- Password: `attendance123`

Additional member accounts are seeded automatically for local testing.

## First Login Checklist

After installation:

1. Open `http://127.0.0.1:8000`
2. Log in using the seeded admin account
3. Open the dashboard
4. Check the users page
5. Check the backups page

## Firebase Configuration

Firebase is optional for local development.

If you want Firebase sync features to work, set these values in `.env`:

```env
FIREBASE_DATABASE_URL=
FIREBASE_DATABASE_SECRET=
```

If those values are empty, the local app can still run normally.

## Useful Commands

Run the installer:

```bash
php artisan app:install --seed
```

Run Laravel:

```bash
php artisan serve
```

Run Vite:

```bash
npm run dev
```

Run all local dev processes:

```bash
composer run dev
```

Run tests:

```bash
php artisan test
```

Run lint and checks:

```bash
composer run lint:check
npm run lint:check
npm run format:check
npm run types:check
```

Build assets:

```bash
npm run build
```

## Portable Windows Package

If you want a copyable package for another Windows computer, build a portable
release on the source machine:

```bash
composer run package:portable
```

That command refreshes the production frontend build and overwrites the previous
portable `dist` output automatically.

If you prefer running the packaging steps manually, use:

```bash
npm run build
php artisan app:package-portable --force
```

Or call the artisan command directly:

```bash
php artisan app:package-portable --force
```

This creates a portable folder in:

```text
dist/portable/duscaff-attendance-portable
```

And, when `ZipArchive` is available, also creates:

```text
dist/portable/duscaff-attendance-portable.zip
```

The portable package includes:

- bundled Laravel `vendor` dependencies
- built frontend assets from `public/build`
- the current SQLite database when `database/database.sqlite` exists
- Windows startup scripts: `prepare-portable.bat` and `start-portable.bat`

If you want a fresh portable distribution with no copied SQLite data, run:

```bash
php artisan app:package-portable --force --without-current-data
```

On the other computer, the target machine only needs PHP `8.2+`, or a portable
PHP runtime copied into a local `php` folder inside the package. Then run:

```text
start-portable.bat
```

If the package starts with a fresh or empty SQLite file, the first run will
also seed the default demo accounts automatically so the copied app is usable
right away.

## Windows EXE Installer

If you want a single Windows setup `.exe`, build the installer on the source machine:

```bash
composer run package:installer
```

That creates:

```text
dist/installer/Duscaff-Attendance-Setup.exe
```

The installer bundles the current local PHP runtime, installs the app into:

```text
%LOCALAPPDATA%\Programs\Duscaff Attendance
```

And creates both Desktop and Start menu shortcuts.

By default the installer ships with a fresh SQLite database so it does not copy your current local app data into the `.exe`.
If you intentionally want to include the current SQLite data, run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File deploy/installer/build-installer.ps1 -IncludeCurrentData
```

Installer-specific notes are also documented in:

- [INSTALLER-README.md](/c:/xampp/htdocs/duscaff-attendance/deploy/installer/INSTALLER-README.md)
## Troubleshooting

### `composer install` fails

Make sure:

- PHP version is `8.2` or newer
- Composer is installed correctly
- required PHP extensions are enabled

### `php artisan app:install --seed` fails

Check:

- `.env` has the correct database connection settings
- MySQL is running if you switched from SQLite to MySQL
- the SQLite driver is enabled in PHP if you are using SQLite

### `npm install` or `npm run dev` fails

Make sure:

- Node.js is installed
- npm is available in your terminal
- you are running the commands from the project root

### Login does not work

Make sure you already ran:

```bash
php artisan app:install --seed
```

## Deployment

Deployment notes are available in:

- [BRANCHING.md](/c:/xampp/htdocs/duscaff-attendance/BRANCHING.md)
- [firebase-cloud-run.md](/c:/xampp/htdocs/duscaff-attendance/docs/deployment/firebase-cloud-run.md)
- [infinityfree.md](/c:/xampp/htdocs/duscaff-attendance/docs/deployment/infinityfree.md)
- [railway.md](/c:/xampp/htdocs/duscaff-attendance/docs/deployment/railway.md)

## Safe GitHub Release Flow

For safer production deploys, use this branch flow:

- `feature/*` and `fix/*` branches merge into `develop`
- `develop` is promoted into `staging`
- `staging` is promoted into `main`
- `main` is the only production deployment branch

Emergency fixes should use `hotfix/*` from `main`, then be merged back into `develop`.

Full setup steps for branch protection and rollback are documented in:

- [BRANCHING.md](/c:/xampp/htdocs/duscaff-attendance/BRANCHING.md)
