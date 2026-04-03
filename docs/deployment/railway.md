# Railway Deployment

This project can be deployed to Railway with:

- one Laravel web service built from the repo `Dockerfile`
- one Railway PostgreSQL service

This repo already includes:

- a Railway config file: `railway.json`
- a Railway-ready env template: `deploy/railway/.env.railway.example`
- a Docker image that listens on Railway's `PORT`
- an automatic Railway healthcheck on `/up`
- a pre-deploy migration command: `php artisan migrate --force`

## 1. Create the Railway project

1. Push this repo to GitHub.
2. In Railway, create a new project from the GitHub repository.
3. Railway will detect the root `Dockerfile` and build the app from it.

Official Railway references:

- https://docs.railway.com/guides/laravel
- https://docs.railway.com/builds/dockerfiles
- https://docs.railway.com/deployments/healthchecks

## 2. Add a PostgreSQL service

Inside the same Railway project:

1. Add a new `PostgreSQL` service.
2. Keep the default generated credentials.

Railway exposes connection variables like:

- `DATABASE_URL`
- `PGHOST`
- `PGPORT`
- `PGUSER`
- `PGPASSWORD`
- `PGDATABASE`

## 3. Configure the Laravel service variables

Open the Laravel service in Railway and use the Variables tab.

The fastest setup is:

1. Open `deploy/railway/.env.railway.example`
2. Paste it into Railway's raw variable editor
3. Replace or fill the required values

Important values:

- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION=pgsql`
- `DB_URL=${{Postgres.DATABASE_URL}}`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `LOG_CHANNEL=stderr`

Generate the Laravel app key locally with:

```bash
php artisan key:generate --show
```

Then paste the returned `base64:...` value into `APP_KEY`.

For `APP_URL`, use Railway's domain variable:

```env
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
```

References:

- https://docs.railway.com/variables
- https://docs.railway.com/reference/variables

## 4. Deploy the app

After the variables are in place, deploy the Laravel service.

This repo's `railway.json` tells Railway to:

- build from `Dockerfile`
- run a healthcheck at `/up`
- run `php artisan migrate --force` before the new deployment goes live

## 5. Seed the first admin accounts

Migrations run automatically during deploy, but seeding should be done once on purpose.

Run this from your machine with the Railway CLI after linking the project:

```bash
railway run php artisan db:seed --force
```

If you want to seed only the main database seeder explicitly:

```bash
railway run php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
```

## 6. Generate a public domain

In the Laravel service:

1. Open `Settings`
2. Open `Networking`
3. Generate a Railway public domain

After that, confirm `APP_URL` uses:

```env
https://${{RAILWAY_PUBLIC_DOMAIN}}
```

Reference:

- https://docs.railway.com/networking/public-networking

## 7. Production notes for this project

- Do not use SQLite on Railway for production. This app defaults to SQLite locally, but Railway deployment should use PostgreSQL.
- Keep `SESSION_DRIVER=database` and `CACHE_STORE=database` so sessions and cache survive container restarts.
- `QUEUE_CONNECTION=sync` is fine for the current app because this project does not require a dedicated queue worker to function.
- `LOG_CHANNEL=stderr` is recommended so logs appear in Railway's log viewer.
- Firebase sync remains optional. Leave `FIREBASE_DATABASE_URL` and `FIREBASE_DATABASE_SECRET` empty if you do not use it.

## 8. Quick checklist

- Railway Laravel service created from this repo
- Railway PostgreSQL service added
- `APP_KEY` set
- `APP_URL` set
- `DB_URL` references `${{Postgres.DATABASE_URL}}`
- first deploy succeeds
- `/up` healthcheck passes
- `railway run php artisan db:seed --force` completed

## Troubleshooting

### App deploys but shows a database error

Check:

- PostgreSQL service exists in the same project
- `DB_CONNECTION=pgsql`
- `DB_URL=${{Postgres.DATABASE_URL}}`

### Deploy fails during pre-deploy migrate

Check:

- `APP_KEY` is set
- PostgreSQL variables are available to the Laravel service
- the database service is healthy

If needed, you can temporarily remove the pre-deploy migration from `railway.json` and run migrations manually with:

```bash
railway run php artisan migrate --force
```

### App boots but styling is missing

That usually means the Docker build did not finish the frontend asset build. Check the build logs for the `npm run build` stage from the Dockerfile.
