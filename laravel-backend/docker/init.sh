#!/bin/sh
# One-shot: prepares the shared volumes and migrates every database before any
# app replica starts. Safe to run on every deploy.
set -eu
cd /app

# stancl/tenancy keeps each school's SQLite file in database_path(), so the data
# volume is mounted over database/. Refresh the code-owned parts from the image
# and never touch the *.sqlite / tenant* data files.
for dir in migrations seeders factories; do
    rm -rf "database/$dir"
    cp -a "database.dist/$dir" "database/$dir"
done
cp -a database.dist/.gitignore database/.gitignore 2>/dev/null || true

for dir in app/public framework/cache/data framework/sessions framework/views logs; do
    mkdir -p "storage/$dir"
done

central_db="${DB_DATABASE:-/app/database/database.sqlite}"
[ -f "$central_db" ] || touch "$central_db"

php artisan migrate --force --no-ansi
php artisan tenants:migrate --force --no-ansi

echo "init: databases migrated"
