#!/bin/sh
set -e

# Ensure the SQLite database file exists only when SQLite is in use
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p "$(dirname "${DB_DATABASE:-/var/data/database.sqlite}")"
    if [ ! -f "${DB_DATABASE:-/var/data/database.sqlite}" ]; then
        touch "${DB_DATABASE:-/var/data/database.sqlite}"
    fi
fi

# Render free instances use an ephemeral filesystem, so production data must
# live in Render Postgres instead of the container-local SQLite file.
if [ -n "${RENDER_SERVICE_ID:-}" ] || [ -n "${RENDER_GIT_COMMIT:-}" ]; then
    if [ "${DB_CONNECTION:-}" != "pgsql" ] || [ -z "${DB_URL:-}" ]; then
        echo "Render must run with DB_CONNECTION=pgsql and a persistent DB_URL. Refusing to start with ephemeral SQLite." >&2
        exit 1
    fi
fi

# Force APP_URL to use https (in case env var was set with http://)
if [ -n "$APP_URL" ]; then
    export APP_URL=$(echo "$APP_URL" | sed 's|^http://|https://|')
fi

# Run deployment tasks only once per deployed Render commit
php artisan app:prepare-render-deployment

# Clear any stale cache from previous deploys
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild cache with correct env
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start the server
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
