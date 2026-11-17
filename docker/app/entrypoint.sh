#!/bin/sh
# Starts the local test copy: waits for the database, builds the tables, loads the demo agency, serves on port 8000.
set -eu
cd /app

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force --no-interaction

until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -q; do
    echo "Waiting for the database..."
    sleep 1
done

php artisan migrate --database=pgsql_owner --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan platform:check-db

if [ "${SEED_DEMO:-0}" = "1" ]; then
    php artisan db:seed --class=DemoSeeder --force --no-interaction
fi

php artisan queue:work --tries=3 --sleep=2 &
exec php artisan serve --no-reload --host=0.0.0.0 --port=8000
