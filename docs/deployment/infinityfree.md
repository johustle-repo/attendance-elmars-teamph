# InfinityFree Deployment

This project can run on InfinityFree, but it needs a shared-hosting style deployment instead of the usual Laravel server workflow.

## Important InfinityFree Limits

Before deploying, keep these InfinityFree free-hosting limits in mind:

- no SSH or terminal access on the server
- no remote MySQL connections on free hosting
- your site must run directly from the hosting account `htdocs` folder
- `.php`, `.html`, and `.js` files over `1 MB` may be deleted automatically
- `.htaccess` files must stay under `10 KB`

Because of those limits, this repo includes an InfinityFree packaging flow that prepares a flat uploadable `htdocs` deployment.

## 1. Build The App Locally

From the project root, make sure dependencies are installed and frontend assets are built:

```bash
composer install
npm install
npm run build
```

If you already have everything installed, you can skip the repeated install commands.

## 2. Prepare The Database Locally

InfinityFree does not allow direct remote MySQL access on the free plan, so the easiest flow is:

1. Run your migrations and seeders locally.
2. Export the local database as an SQL file.
3. Import that SQL file into the InfinityFree database using phpMyAdmin.

Local setup command:

```bash
php artisan migrate --seed
```

Export options:

- use phpMyAdmin export if your local database is in XAMPP/MySQL
- use any local MySQL dump tool if you prefer command-line export

After creating the SQL file, keep it ready for the import step later.

## 3. Create The InfinityFree Environment File

Copy the template file:

```powershell
Copy-Item deploy\infinityfree\.env.infinityfree.example deploy\infinityfree\.env.infinityfree
```

Then edit `deploy/infinityfree/.env.infinityfree` and update at least these values:

- `APP_KEY`
- `APP_URL`
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `FIREBASE_DATABASE_URL` and `FIREBASE_DATABASE_SECRET` if you use Firebase sync

Generate an app key locally if needed:

```bash
php artisan key:generate --show
```

Copy the generated `base64:...` value into the InfinityFree environment file.

Recommended InfinityFree-safe settings are already included in the template:

- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `APP_DEBUG=false`

## 4. Create The Upload Package

Run:

```bash
php deploy/infinityfree/prepare.php
```

That script will:

- build `.infinityfree-deploy/htdocs`
- copy the Laravel runtime folders into that package
- copy your InfinityFree `index.php` and `.htaccess`
- copy your built Vite assets
- warn you about files that exceed InfinityFree file-size limits

If the script warns that a PHP or JS file is over `1 MB`, run:

```bash
composer install --no-dev --optimize-autoloader --working-dir=.infinityfree-deploy/htdocs
```

That command optimizes the generated upload package without changing your main local development dependencies.

## 5. Create The InfinityFree Database

Inside the InfinityFree control panel:

1. Create a new MySQL database.
2. Open phpMyAdmin for that database.
3. Import the SQL file you exported from your local machine.

After that, confirm the database credentials match the values you placed in `deploy/infinityfree/.env.infinityfree`.

## 6. Upload The Project

Use InfinityFree File Manager or FTP and upload the contents of:

```text
.infinityfree-deploy/htdocs
```

Important:

- upload the contents of that folder into the hosting account `htdocs`
- do not upload the outer `.infinityfree-deploy` folder itself
- the packaged `index.php` must end up directly inside InfinityFree `htdocs`

## 7. Final Check

After upload:

1. Open your InfinityFree site URL.
2. Try logging in with a seeded admin account.
3. Open the dashboard.
4. Test attendance scanning.
5. Open the backup export page and verify downloads work.

## Troubleshooting

### White page or 500 error

Check:

- the packaged `.env` file has the correct database values
- `APP_KEY` is set
- all packaged files were uploaded into `htdocs`
- the Vite `build` folder was uploaded completely

### Database connection error

Check:

- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
- the SQL import finished successfully in phpMyAdmin

### Missing assets or broken styling

Check that the packaged `build` folder exists inside InfinityFree `htdocs`.

### InfinityFree deletes files after upload

This usually means one or more `.php`, `.html`, or `.js` files exceeded the host file-size limit. Optimize the generated package with:

```bash
composer install --no-dev --optimize-autoloader --working-dir=.infinityfree-deploy/htdocs
```