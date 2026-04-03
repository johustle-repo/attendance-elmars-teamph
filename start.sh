#!/bin/sh
set -e

# Ensure SQLite database file exists on the persistent disk
if [ ! -f "${DB_DATABASE:-/var/data/database.sqlite}" ]; then
    touch "${DB_DATABASE:-/var/data/database.sqlite}"
fi

# Run migrations
php artisan migrate --force

# Cache config/routes for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start the server
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
