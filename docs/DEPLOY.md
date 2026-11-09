# Panduan Deploy (VPS)

Target: satu VPS Ubuntu 22.04/24.04 (2 vCPU / 2 GB RAM cukup untuk beberapa tenant awal). Stack: Nginx, PHP 8.3-FPM, MySQL 8, Supervisor, Node (hanya saat build).

Berkas pendukung ada di folder [`deploy/`](../deploy): `nginx/`, `supervisor/`, `cron/`, `backup.sh`, `deploy.sh`, serta `.env.production.example` di root.

## 1. Domain & DNS

1. Beli/siapkan domain induk, misal `domainkamu.com`.
2. Buat record DNS ke IP VPS:
   - `A  domainkamu.com  -> IP_VPS`
   - `A  *.domainkamu.com -> IP_VPS` (wildcard, untuk semua subdomain tenant)
3. Subdomain `www`, `app`, `platform`, `admin` dicadangkan (lihat `config/booking.php`), tidak bisa dipakai tenant.

## 2. Paket server

```bash
sudo apt update && sudo apt install -y nginx mysql-server supervisor git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-intl php8.3-gd php8.3-bcmath
# Composer: https://getcomposer.org/download/   Node 22 LTS: https://nodejs.org (untuk npm run build)
```

Ekstensi `intl` dan `gd` dibutuhkan Filament dan pembuatan gambar QR.

## 3. Database

```sql
CREATE DATABASE booking_villa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'booking_villa'@'127.0.0.1' IDENTIFIED BY 'KATA_SANDI_KUAT';
GRANT ALL PRIVILEGES ON booking_villa.* TO 'booking_villa'@'127.0.0.1';
```

## 4. Kode & konfigurasi

```bash
sudo mkdir -p /var/www/booking-villa && sudo chown $USER:www-data /var/www/booking-villa
git clone https://github.com/iktkarmita/booking-villa /var/www/booking-villa
cd /var/www/booking-villa
cp .env.production.example .env      # lalu isi semua nilai bertanda GANTI
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan db:seed --class=SetupSeeder --force   # membuat super admin dari BOOKING_ADMIN_*
npm ci && npm run build
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Setelah `.env` final: `chmod 640 .env`. **Jangan** isi `APP_ENV=local` di server, karena akun demo hanya dibuat di environment `local`.

## 5. SSL wildcard

Sertifikat wildcard Let's Encrypt wajib memakai validasi DNS (DNS-01). Contoh dengan Cloudflare:

```bash
sudo apt install -y certbot python3-certbot-dns-cloudflare
sudo install -m 600 /dev/null /root/.secrets/cloudflare.ini   # isi: dns_cloudflare_api_token = TOKEN
sudo certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d domainkamu.com -d '*.domainkamu.com'
```

Provider DNS lain: pakai plugin certbot yang sesuai (`certbot-dns-*`). Perpanjangan otomatis berjalan lewat timer certbot; uji dengan `sudo certbot renew --dry-run`.

## 6. Nginx

```bash
sudo cp deploy/nginx/booking-villa.conf /etc/nginx/sites-available/booking-villa
# edit: domainkamu.com, path, versi socket PHP-FPM
sudo ln -s /etc/nginx/sites-available/booking-villa /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Aplikasi membaca IP dan https dari header proxy lewat `TRUSTED_PROXIES` (default `.env.production.example`: `127.0.0.1`). Tanpa ini, rate limit per IP dan URL https bisa salah.

## 7. Queue worker & scheduler

```bash
sudo cp deploy/supervisor/booking-villa-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo cp deploy/cron/booking-villa /etc/cron.d/booking-villa
```

Scheduler menjalankan: `bookings:expire` (tiap menit), `bookings:remind-balance` (09:00), `subscriptions:process` (01:00). **Tanpa cron ini booking pending tidak pernah kedaluwarsa dan langganan tidak ditagih.** Jam jadwal dan "hari ini" (validasi tanggal check-in) mengikuti `APP_TIMEZONE` (default `UTC`). Isi sesuai lokasi villa, misal `Asia/Makassar`; satu zona waktu untuk seluruh platform.

## 8. Backup

`deploy/backup.sh` (dipasang lewat cron di atas) membuat dump MySQL dan arsip `storage/app/public` setiap hari pukul 02:30, simpan 14 hari di `/var/backups/booking-villa`. Tambahkan penyalinan ke luar server (rclone/rsync/S3). Uji pemulihan sekali sebelum go-live:

```bash
gunzip -c db-XXXX.sql.gz | mysql -u root booking_villa_restore_test
```

## 9. Rilis berikutnya

```bash
cd /var/www/booking-villa && bash deploy/deploy.sh
```

Skrip: maintenance mode, `git reset --hard origin/main` (perubahan lokal di server hilang), composer, build aset, migrasi, cache config/route/view, restart worker. Rollback: `git reset --hard <commit lama>`, `bash deploy/deploy.sh` dengan `BRANCH` diarahkan ke commit itu, dan pulihkan backup DB bila migrasi perlu dibatalkan.

## 10. Onboarding tenant baru

1. Login `https://domainkamu.com/platform` sebagai super admin.
2. Buat tenant (slug = subdomain), pilih paket, buat langganan, lalu buat user dengan peran `owner`.
3. Owner login di `https://domainkamu.com/admin`, mengisi pengaturan pembayaran (kredensial gateway sendiri), villa, kamar.
4. Tambahkan URL webhook di dashboard gateway owner: `https://domainkamu.com/webhooks/payment/{gateway}/{slug-tenant}`.
5. Situs tamu: `https://{subdomain}.domainkamu.com`.

## Checklist go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` terisi
- [ ] `BOOKING_ADMIN_PASSWORD` kuat; akun demo tidak ada
- [ ] HTTPS wildcard aktif, HTTP dialihkan ke HTTPS
- [ ] Cron scheduler dan worker supervisor berjalan (`supervisorctl status`)
- [ ] Email keluar diuji (SMTP sungguhan, bukan `log`)
- [ ] Backup terjadwal dan pemulihan sudah diuji
- [ ] Webhook gateway diuji dengan transaksi kecil
- [ ] `php artisan test` hijau di commit yang dirilis
