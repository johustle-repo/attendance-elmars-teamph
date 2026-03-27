# Firebase Hosting + Cloud Run

This Laravel app should be hosted with:

- Firebase Hosting for the public domain and CDN
- Google Cloud Run for the Laravel PHP application
- Cloud SQL for the production database

## 1. Prepare Google Cloud and Firebase

1. Create or select your Firebase project.
2. Upgrade the Firebase project to the Blaze plan.
3. Make sure the same project is available in Google Cloud.
4. Enable these services:
   - Cloud Run
   - Artifact Registry
   - Cloud Build
   - Cloud SQL Admin API
   - Firebase Hosting API

## 2. Create a production database

Recommended options:

- Cloud SQL for MySQL
- Cloud SQL for PostgreSQL

Set your Laravel database values from that instance:

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

If you use the Cloud SQL Auth Proxy connection in Cloud Run, you can also use:

- `DB_SOCKET=/cloudsql/PROJECT_ID:REGION:INSTANCE_NAME`

## 3. Build and deploy Laravel to Cloud Run

From the project root:

```bash
gcloud auth login
gcloud config set project YOUR_PROJECT_ID

gcloud run deploy attendance-elmars-teamph \
  --source . \
  --region asia-southeast1 \
  --allow-unauthenticated
```

After the first deploy, set the production env vars:

```bash
gcloud run services update attendance-elmars-teamph \
  --region asia-southeast1 \
  --update-env-vars APP_ENV=production,APP_DEBUG=false,APP_URL=https://YOUR_DOMAIN,APP_TIMEZONE=Asia/Manila,LOG_CHANNEL=stderr,DB_CONNECTION=mysql,DB_HOST=YOUR_DB_HOST,DB_PORT=3306,DB_DATABASE=YOUR_DB_NAME,DB_USERNAME=YOUR_DB_USER,DB_PASSWORD=YOUR_DB_PASSWORD,FIREBASE_DATABASE_URL=YOUR_FIREBASE_URL,FIREBASE_DATABASE_SECRET=YOUR_FIREBASE_SECRET
```

If you use Cloud SQL with sockets:

```bash
gcloud run services update attendance-elmars-teamph \
  --region asia-southeast1 \
  --add-cloudsql-instances PROJECT_ID:REGION:INSTANCE_NAME \
  --update-env-vars DB_CONNECTION=mysql,DB_SOCKET=/cloudsql/PROJECT_ID:REGION:INSTANCE_NAME,DB_DATABASE=YOUR_DB_NAME,DB_USERNAME=YOUR_DB_USER,DB_PASSWORD=YOUR_DB_PASSWORD
```

## 4. Generate the Laravel app key

Generate a key once and store it in Cloud Run:

```bash
php artisan key:generate --show
```

Then add the returned value as:

- `APP_KEY=base64:...`

## 5. Run database migration

Run migrations against the production database:

```bash
gcloud run jobs create attendance-migrate \
  --image asia-southeast1-docker.pkg.dev/YOUR_PROJECT_ID/cloud-run-source-deploy/attendance-elmars-teamph \
  --region asia-southeast1 \
  --set-env-vars APP_ENV=production,APP_DEBUG=false,APP_KEY=YOUR_APP_KEY,DB_CONNECTION=mysql,DB_HOST=YOUR_DB_HOST,DB_PORT=3306,DB_DATABASE=YOUR_DB_NAME,DB_USERNAME=YOUR_DB_USER,DB_PASSWORD=YOUR_DB_PASSWORD,FIREBASE_DATABASE_URL=YOUR_FIREBASE_URL,FIREBASE_DATABASE_SECRET=YOUR_FIREBASE_SECRET \
  --command php \
  --args artisan,migrate,--force

gcloud run jobs execute attendance-migrate --region asia-southeast1
```

If the job already exists, use:

```bash
gcloud run jobs update attendance-migrate ...
```

## 6. Deploy Firebase Hosting in front of Cloud Run

Install the Firebase CLI if needed:

```bash
npm install -g firebase-tools
```

Login and connect the project:

```bash
firebase login
firebase use --add
```

This repo already includes a `firebase.json` rewrite that sends all traffic to:

- service: `attendance-elmars-teamph`
- region: `asia-southeast1`

Deploy Hosting:

```bash
firebase deploy --only hosting
```

## 7. Connect a custom domain

In Firebase Console:

1. Open Hosting
2. Add custom domain
3. Follow the DNS verification steps

After DNS propagation, update Cloud Run:

- `APP_URL=https://your-domain.com`

## 8. Production checklist

- `APP_DEBUG=false`
- `APP_ENV=production`
- `APP_KEY` is set
- database credentials are set
- Firebase credentials are set
- `php artisan migrate --force` has run
- Cloud Run service is public
- Firebase Hosting rewrite points to the correct Cloud Run service

## Notes for this project

- `.env` is not deployed; only `.env.example` is kept in git.
- Super admin visibility rules are app-level behavior and will work the same online.
- Firebase Realtime Database is already used as an online sync target by the app. Firebase Hosting is only the web entrypoint.
