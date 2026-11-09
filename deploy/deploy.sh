#!/usr/bin/env bash
# Rilis versi terbaru di server. Jalankan sebagai user deploy: bash deploy/deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/booking-villa}"
BRANCH="${BRANCH:-main}"
cd "$APP_DIR"

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize || true

# Muat ulang worker agar memakai kode baru
php artisan queue:restart
sudo -n supervisorctl restart booking-villa-worker:* >/dev/null 2>&1 || true

echo "Rilis selesai."
