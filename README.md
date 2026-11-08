# Booking Villa (Multi-Tenant SaaS)

Sistem booking villa berlangganan: tiap owner villa = 1 tenant dengan banyak villa dan kamar. Pembayaran tamu lewat QRIS dinamis (gateway milik masing-masing owner). Admin pakai Filament, situs publik pakai Livewire, bahasa ID/EN.

Rancangan lengkap (ERD, alur, fase pengerjaan): [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

**Stack:** Laravel 13 · PHP 8.3+ · Filament 5 · Livewire 4 · MySQL (SQLite untuk lokal/tes).

## Status

Fase 0 (kerangka + tenancy). Kode ditulis tanpa bisa dijalankan di lingkungan penulis, jadi **jalankan `php artisan test` pertama kali** dan laporkan error bila ada.

Sudah ada:
- Multi-tenancy satu database (`tenant_id`, `BelongsToTenant`, `CurrentTenant`, `ResolveTenant` dari subdomain/domain sendiri)
- Panel **platform** (`/platform`, super admin): tenant, paket, langganan, pengguna
- Panel **owner** (`/admin/{slug-tenant}`): dashboard ringkasan paket
- Paket per jumlah kamar (Starter 10 / Growth 20 / Business 30) lewat `PlanSeeder`
- Bahasa ID/EN (`lang/`), pilihan bahasa di sesi (`/lang/id`, `/lang/en`)
- Tes: isolasi tenant, resolusi tenant, bahasa, paket/langganan, akses panel

## Setup lokal

Syarat: PHP 8.3+, Composer, Git. Lalu dari folder repo:

```bash
bash scripts/bootstrap.sh
```

Skrip ini membuat kerangka Laravel 13 tanpa menimpa file repo ini, memasang Filament 5, mendaftarkan provider, menyiapkan `.env`, migrasi, dan seeder. Di Windows jalankan lewat Git Bash atau WSL.

Setelah selesai:

```bash
php artisan test
php artisan serve
```

| Alamat (lokal) | Fungsi | Akun |
|---|---|---|
| `http://localhost:8000/platform` | Panel super admin | `admin@example.com` / `password` |
| `http://localhost:8000/admin` | Panel owner | `owner@example.com` / `password` |
| `http://demo.localhost:8000/` | Situs tenant demo | - |

Browser modern otomatis mengarahkan `*.localhost` ke komputer sendiri. Akun demo hanya dibuat di environment `local`. Di server sungguhan isi `BOOKING_ADMIN_EMAIL` dan `BOOKING_ADMIN_PASSWORD` di `.env` sebelum menjalankan `php artisan db:seed --class=SetupSeeder`.

Setelah bootstrap, commit juga `composer.json`, `composer.lock`, dan file kerangka lainnya.

## Konfigurasi

| Variabel `.env` | Fungsi |
|---|---|
| `APP_BASE_DOMAIN` | Domain induk. Situs tenant = `{subdomain}.{APP_BASE_DOMAIN}`. Lokal: `localhost` |
| `BOOKING_CENTRAL_HOSTS` | Host lain yang dianggap pusat (koma), default `127.0.0.1` |
| `BOOKING_ADMIN_EMAIL`, `BOOKING_ADMIN_PASSWORD` | Akun super admin untuk `SetupSeeder` |

Produksi membutuhkan wildcard DNS (`*.domainkamu.com`) dan SSL wildcard.

## Aturan penting untuk pengembang

1. Model yang punya `tenant_id` **wajib** memakai trait `App\Models\Concerns\BelongsToTenant`.
2. `Model::withoutTenancy()` hanya untuk kebutuhan lintas tenant yang disengaja (mis. panel platform).
3. Di job/command gunakan `app(CurrentTenant::class)->runAs($tenant, fn () => ...)`.
4. Kredensial gateway/WhatsApp disimpan terenkripsi (cast `encrypted:array`), jangan dicatat ke log.
5. Logika bisnis di Service/Action class, bukan di controller atau komponen Livewire.

## Struktur

```
app/Enums/                 Enum status
app/Models/Concerns/       BelongsToTenant
app/Models/Scopes/         TenantScope
app/Support/CurrentTenant  Tenant aktif per request
app/Http/Middleware/       ResolveTenant, EnsureTenantActive, SetLocale, SyncCurrentTenant
app/Providers/             BookingServiceProvider + panel Filament
app/Filament/Platform/     Resource super admin
app/Filament/Admin/        Widget & resource owner (resource villa menyusul di fase 1)
database/                  migrasi, factory, seeder
lang/{id,en}/booking.php   Terjemahan
tests/Feature/             Tes
```

## Fase berikutnya

Lihat tabel fase di `docs/ARCHITECTURE.md`. Berikutnya: **Fase 1**, villa + kamar (CRUD, foto, fasilitas) dengan pengecekan kuota paket.
