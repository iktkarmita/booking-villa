#!/usr/bin/env bash
# Backup harian: dump MySQL + berkas unggahan (bukti bayar, foto villa). Simpan 14 hari.
# Baca kredensial dari .env aplikasi. Jalankan sebagai root lewat cron.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/booking-villa}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/booking-villa}"
KEEP_DAYS="${KEEP_DAYS:-14}"

env_get() { grep -E "^$1=" "$APP_DIR/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e 's/[[:space:]]*#.*$//'; }

DB_HOST="$(env_get DB_HOST)"; DB_PORT="$(env_get DB_PORT)"
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"; DB_PASS="$(env_get DB_PASSWORD)"

STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# Kata sandi lewat file sementara agar tidak tampil di daftar proses
CNF="$(mktemp)"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$DB_USER" "$DB_PASS" "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" > "$CNF"

mysqldump --defaults-extra-file="$CNF" --single-transaction --routines "$DB_NAME" | gzip -9 > "$BACKUP_DIR/db-$STAMP.sql.gz"
tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" -C "$APP_DIR/storage/app" public

find "$BACKUP_DIR" -type f \( -name 'db-*.sql.gz' -o -name 'files-*.tar.gz' \) -mtime +"$KEEP_DAYS" -delete
echo "[$(date -Is)] backup selesai: $STAMP"

# Disarankan: salin ke luar server (rclone/rsync/S3), karena backup di server yang sama tidak menolong bila server hilang.
