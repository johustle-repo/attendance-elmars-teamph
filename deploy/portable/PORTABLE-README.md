# Portable Package

This package is meant to be copied to another Windows computer and started locally.

## What the target computer needs

- PHP 8.2 or newer available as `php.exe` in `PATH`
- Or a portable PHP runtime copied into a local `php` folder beside `start-portable.bat`

Node.js, npm, Composer, and Vite are not required on the target computer.

## First run on the other computer

1. Copy the whole portable folder to the other computer.
2. Double-click `start-portable.bat`.
3. The package will:
   - create `.env` from `.env.portable.example` if needed
   - create `database/database.sqlite` if it does not exist
   - clear machine-specific caches
   - run `php artisan app:install --force`, adding seeding automatically when the portable database is fresh or empty
   - seed the default demo accounts automatically when the portable database is fresh or empty
   - start the local Laravel server on `http://127.0.0.1:8000`

## Notes

- If this package was created from a machine that already had `database/database.sqlite`, that current SQLite data is included.
- If you want a fully self-contained folder, place a portable PHP runtime at `php/php.exe` inside the package.
- To stop the app, close the console window opened by `start-portable.bat`.