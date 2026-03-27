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

### 1. Install PHP and frontend dependencies

```bash
composer install
npm install
```

### 2. Create the environment file

Linux / macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 3. Choose your database

You can use either SQLite or MySQL.

#### Option A: SQLite

This is the fastest and easiest local setup.

Create the database file:

Linux / macOS:

```bash
touch database/database.sqlite
```

Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File -Force
```

Make sure `.env` contains:

```env
DB_CONNECTION=sqlite
```

You do not need to set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, or `DB_PASSWORD` for the default SQLite setup.

#### Option B: MySQL / XAMPP

If you are using XAMPP on another computer:

1. Start `Apache` and `MySQL` from the XAMPP Control Panel.
2. Create a database in phpMyAdmin named `attendance_elmars_teamph`.

SQL example:

```sql
CREATE DATABASE attendance_elmars_teamph;
```

Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_elmars_teamph
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Generate the Laravel app key

```bash
php artisan key:generate
```

### 5. Run migrations and seed the app

```bash
php artisan migrate --seed
```

This will:

- create the database tables
- create the cache, jobs, and session tables
- seed the default admin and member accounts

### 6. Build or run frontend assets

For development:

```bash
npm run dev
```

For a production-style local build:

```bash
npm run build
```

### 7. Start the Laravel app

In a separate terminal:

```bash
php artisan serve
```

Then open:

```text
http://127.0.0.1:8000
```

## Quick Install Shortcut

If you want the shortest setup path:

```bash
composer run setup
php artisan db:seed
```

Then run:

```bash
php artisan serve
npm run dev
```

Note:

- `composer run setup` installs dependencies and runs migrations
- it does not seed demo users by itself, so `php artisan db:seed` is still needed if you want sample accounts

## Development Mode

You can also run the app with the combined development command:

```bash
composer run dev
```

That starts:

- Laravel server
- queue listener
- Vite dev server

## Seeded Accounts

After running `php artisan migrate --seed` or `php artisan db:seed`, these demo accounts are available:

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

## Troubleshooting

### `composer install` fails

Make sure:

- PHP version is `8.2` or newer
- Composer is installed correctly
- required PHP extensions are enabled

### `npm install` or `npm run dev` fails

Make sure:

- Node.js is installed
- npm is available in your terminal
- you are running the commands from the project root

### Database connection error

Check `.env` and confirm:

- the selected database driver is correct
- MySQL is running if you use XAMPP
- the SQLite file exists if you use SQLite

### Login does not work

Make sure you already ran:

```bash
php artisan migrate --seed
```

## Deployment

Cloud Run and Firebase Hosting deployment notes are available in:

- [firebase-cloud-run.md](/c:/xampp/htdocs/duscaff-attendance/docs/deployment/firebase-cloud-run.md)
