#!/bin/sh
set -e

# Ensure the data directory and SQLite database file exist
mkdir -p "$(dirname "${DB_DATABASE:-/var/data/database.sqlite}")"
if [ ! -f "${DB_DATABASE:-/var/data/database.sqlite}" ]; then
    touch "${DB_DATABASE:-/var/data/database.sqlite}"
fi

# Force APP_URL to use https (in case env var was set with http://)
if [ -n "$APP_URL" ]; then
    export APP_URL=$(echo "$APP_URL" | sed 's|^http://|https://|')
fi

# Run migrations
php artisan migrate --force

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
