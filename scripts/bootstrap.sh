#!/usr/bin/env bash
#
# Menyiapkan proyek di komputer lokal:
#   1. membuat kerangka Laravel 13 (tanpa menimpa file yang sudah ada di repo ini)
#   2. memasang Filament 5
#   3. mendaftarkan provider, menyiapkan .env, migrasi, dan seeder
#
# Pemakaian (dari folder repo):  bash scripts/bootstrap.sh
# Butuh: PHP 8.3+, Composer, ekstensi PHP umum Laravel (mbstring, intl, pdo_sqlite/pdo_mysql, zip, ...).

set -euo pipefail
cd "$(dirname "$0")/.."

need() { command -v "$1" >/dev/null 2>&1 || { echo "ERROR: '$1' tidak ditemukan."; exit 1; }; }
need php
need composer

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || { echo "ERROR: Laravel 13 butuh PHP 8.3+ (terpasang: $(php -r 'echo PHP_VERSION;'))."; exit 1; }

# Ubah/tambah KEY=VALUE di file env
set_env() {
  local file="$1" key="$2" value="$3"
  [ -f "$file" ] || return 0
  if grep -q "^${key}=" "$file"; then
    awk -v k="$key" -v v="$value" 'BEGIN{FS=OFS="="} $1==k{print k"="v; next}{print}' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$file"
  fi
}

if [ ! -f artisan ]; then
  echo "==> Membuat kerangka Laravel 13..."
  TMP="$(mktemp -d)"
  composer create-project laravel/laravel:^13.0 "$TMP/app" --prefer-dist --no-interaction
  # -n: jangan menimpa file milik repo ini (model, routes, migrasi, dst.)
  cp -Rn "$TMP/app/." . || true
  rm -rf "$TMP"
else
  echo "==> Kerangka Laravel sudah ada, dilewati."
fi

echo "==> Memasang Filament 5..."
composer require filament/filament:"^5.0" -W --no-interaction

echo "==> Mendaftarkan provider..."
php scripts/register-providers.php

echo "==> Menyiapkan .env..."
[ -f .env ] || cp .env.example .env
for f in .env .env.example; do
  set_env "$f" APP_LOCALE id
  set_env "$f" APP_FALLBACK_LOCALE en
  set_env "$f" APP_BASE_DOMAIN localhost
done
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --no-interaction

echo "==> Migrasi dan seeder awal..."
php artisan migrate --force --no-interaction
php artisan db:seed --class=SetupSeeder --force --no-interaction

echo "==> Aset Filament..."
php artisan filament:assets --no-interaction || true
php artisan storage:link --no-interaction || true

cat <<'EOF'

Selesai. Langkah berikutnya:
  php artisan test          # jalankan tes
  php artisan serve         # jalankan server lokal

Alamat (lokal):
  Panel platform : http://localhost:8000/platform   (admin@example.com / password)
  Panel owner    : http://localhost:8000/admin      (owner@example.com / password)
  Situs tenant   : http://demo.localhost:8000/

Ganti password akun di atas sebelum dipakai di server sungguhan.
Jangan lupa commit hasil bootstrap (composer.json, composer.lock, dst.).
EOF
