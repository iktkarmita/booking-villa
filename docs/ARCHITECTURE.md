# Arsitektur Booking Villa (Multi-Tenant SaaS)

Setiap **owner villa = 1 tenant** (1 akun login) yang berlangganan. Satu tenant boleh punya **banyak villa**, dan tiap villa punya **kamar/unit** yang bisa dipesan. Platform dikelola oleh kamu (super admin).

**Stack:** Laravel 13 (PHP 8.3+) · MySQL · **Filament 5** (admin) · **Livewire 4** (situs publik) · queue & scheduler Laravel · QRIS dinamis via payment gateway · bahasa **ID/EN**.

Prinsip biar gampang maintenance:
1. Logika bisnis di **Service/Action class**, controller/Livewire tipis.
2. Gateway pembayaran dan pengirim WhatsApp dibungkus **interface**, vendor bisa diganti per tenant.
3. Anti double booking dijaga **di database** (unique key per kamar per malam).
4. Status pakai **Enum**, perubahan status hanya lewat satu service.
5. Webhook dicatat dan **idempotent**.
6. Data tenant terisolasi lewat **global scope `tenant_id`** (trait `BelongsToTenant`), jangan pernah query tanpa scope.

---

## 1. Model Multi-Tenant & Langganan

- **Satu database, kolom `tenant_id`** di semua tabel data tenant.
- **Dua panel Filament:**
  - `/platform` : super admin (kamu). Kelola tenant, paket, invoice, suspend/aktifkan.
  - `/admin` : owner villa (memakai *tenancy* bawaan Filament). Kelola villa, kamar, booking, kalender, pengaturan, refund.
- **Situs publik tiap tenant** dikenali dari subdomain: `namavilla.domainkamu.com` (domain sendiri milik kamu dipakai sebagai induk; butuh **wildcard DNS + SSL wildcard**, VPS paling mudah). Custom domain milik client bisa menyusul.
- Satu user bisa menjadi staf di beberapa tenant (tabel `tenant_user`, role `owner|staff`).

### Paket langganan (berdasarkan total kamar)
| Paket | `max_rooms` | Harga/bulan |
|---|---|---|
| Starter | 10 | Rp150.000 |
| Growth | 20 | Rp250.000 |
| Business (usulan) | 30 | Rp350.000 |

Isi tabel `plans` bisa diubah super admin; tidak ditulis mati di kode.

Aturan:
- Yang dihitung = **jumlah kamar/unit aktif di semua villa milik tenant**. Villa yang disewa utuh = 1 kamar/unit ("Seluruh villa").
- Jumlah villa tidak dibatasi.
- Saat owner menambah kamar dan melebihi `max_rooms`, sistem menolak dan menawarkan upgrade paket. Downgrade ditolak jika kamar aktif melebihi batas paket baru.
- Scheduler harian: buat invoice, kirim pengingat, beri masa tenggang, lalu **suspend** tenant yang menunggak (booking baru ditutup, data aman).
- Tagihan langganan dibayar ke **akun gateway platform (milik kamu)**, terpisah dari uang tamu. Biaya langganan sudah termasuk maintenance dan support.

### Uang tamu → langsung ke owner
Tiap tenant mengisi **kredensial gateway miliknya sendiri** (terenkripsi). Dana pembayaran tamu masuk ke rekening owner, bukan ke kamu. Webhook per tenant: `/webhooks/payment/{gateway}/{tenant}` supaya signature diverifikasi dengan kredensial tenant yang benar.

---

## 2. Database (ERD)

```mermaid
erDiagram
    tenants ||--o{ tenant_user : punya
    users ||--o{ tenant_user : anggota
    tenants ||--|| tenant_settings : punya
    tenants ||--o{ subscriptions : berlangganan
    plans ||--o{ subscriptions : dipakai
    subscriptions ||--o{ subscription_invoices : ditagih
    tenants ||--o{ villas : punya
    tenants ||--o{ guests : punya
    tenants ||--o{ bookings : punya
    villas ||--o{ rooms : "terdiri dari"
    villas ||--o{ villa_images : punya
    villas }o--o{ amenities : amenity_villa
    rooms ||--o{ seasonal_prices : punya
    rooms ||--o{ calendar_days : punya
    rooms ||--o{ bookings : dipesan
    guests ||--o{ bookings : memesan
    bookings ||--o{ calendar_days : mengunci
    bookings ||--o{ payments : punya
    bookings ||--o{ refunds : diajukan
    payments ||--o{ payment_webhook_logs : dicatat

    tenants {
        bigint id PK
        string name
        string slug UK
        string subdomain UK
        string custom_domain UK
        string status "active|suspended|trial"
        string default_locale "id|en"
        string timezone
        string currency "IDR"
    }
    users {
        bigint id PK
        string name
        string email UK
        string password
        boolean is_super_admin
    }
    tenant_user {
        bigint tenant_id FK
        bigint user_id FK
        string role "owner|staff"
    }
    tenant_settings {
        bigint id PK
        bigint tenant_id FK
        string payment_mode "full|dp|both"
        tinyint dp_percent
        tinyint balance_due_days_before_checkin
        string gateway "ipaymu|duitku|manual"
        text gateway_credentials "terenkripsi"
        string wa_number "nomor owner"
        string wa_driver "link|fonnte|meta"
        text wa_credentials "terenkripsi"
        string notify_email
        text cancellation_policy "ID/EN, tampil ke tamu"
    }
    plans {
        bigint id PK
        string name
        decimal price
        string interval "month|year"
        int max_rooms
        json features
        boolean is_active
    }
    subscriptions {
        bigint id PK
        bigint tenant_id FK
        bigint plan_id FK
        string status "trial|active|past_due|canceled"
        date starts_at
        date ends_at
        date grace_until
    }
    subscription_invoices {
        bigint id PK
        bigint subscription_id FK
        string number UK
        decimal amount
        string status "unpaid|paid|void"
        date due_date
        datetime paid_at
        string gateway_ref
    }
    villas {
        bigint id PK
        bigint tenant_id FK
        json name "ID/EN"
        string slug
        json description "ID/EN"
        string address
        time check_in_time
        time check_out_time
        boolean is_active
    }
    rooms {
        bigint id PK
        bigint tenant_id FK
        bigint villa_id FK
        json name "ID/EN, contoh Kamar Deluxe / Seluruh villa"
        tinyint bedrooms
        tinyint bathrooms
        tinyint max_guests
        decimal base_price "per malam"
        tinyint min_nights
        boolean is_active "dihitung ke kuota paket"
    }
    villa_images {
        bigint id PK
        bigint villa_id FK
        string path
        int sort_order
    }
    amenities {
        bigint id PK
        json name "ID/EN"
        string icon
    }
    seasonal_prices {
        bigint id PK
        bigint room_id FK
        string label
        date start_date
        date end_date
        decimal price_per_night
        tinyint min_nights
    }
    guests {
        bigint id PK
        bigint tenant_id FK
        string name
        string email
        string phone
    }
    bookings {
        bigint id PK
        bigint tenant_id FK
        string code UK
        bigint room_id FK
        bigint guest_id FK
        date check_in
        date check_out
        tinyint nights
        tinyint guests_count
        string status "pending_payment|confirmed|checked_in|completed|expired|cancelled"
        string payment_status "unpaid|partial|paid|refunded"
        string payment_option "full|dp"
        decimal total
        decimal dp_amount
        decimal paid_amount
        date balance_due_date
        string locale "id|en"
        string source "web|admin|ota"
        datetime expires_at
        datetime cancelled_at
        string cancel_reason
        text notes
    }
    calendar_days {
        bigint id PK
        bigint room_id FK
        date date
        string type "booking|block"
        bigint booking_id FK
        string note
    }
    payments {
        bigint id PK
        bigint tenant_id FK
        bigint booking_id FK
        string type "full|dp|balance"
        string gateway
        string gateway_ref UK
        string method "qris|manual"
        decimal amount
        string status "pending|paid|expired|failed|refunded"
        text qr_string
        datetime expires_at
        datetime paid_at
        json raw_response
    }
    payment_webhook_logs {
        bigint id PK
        bigint payment_id FK
        string gateway
        json payload
        boolean signature_valid
        datetime processed_at
    }
    refunds {
        bigint id PK
        bigint tenant_id FK
        bigint booking_id FK
        string requested_by "guest|owner"
        text reason
        string status "requested|approved|rejected|refunded"
        decimal amount_approved
        string dest_bank "bank atau e-wallet"
        string dest_account_name
        text dest_account_number "terenkripsi"
        string proof_path "bukti transfer"
        text owner_note
        datetime decided_at
        datetime refunded_at
    }
```

### Catatan desain
- **Hierarki:** `tenant → villa → kamar (rooms)`. Yang dipesan tamu adalah **kamar/unit**. Villa sewa utuh cukup punya 1 kamar ("Seluruh villa"). Foto dan fasilitas melekat di villa.
- **`calendar_days` + `UNIQUE(room_id, date)`** = kunci anti double booking. 1 kamar 1 malam = 1 baris. Dua pemesan di malam yang sama: salah satu gagal di database. Malam yang dihitung adalah `check_in` sampai sebelum `check_out`.
- Blok tanggal oleh owner = baris `type = block`. Booking batal/kadaluarsa = barisnya dihapus.
- Versi awal: **1 booking = 1 kamar**. Tamu yang ingin dua kamar membuat dua booking. Keranjang multi-kamar (`booking_items`) menyusul bila diminta client.
- **Harga dikunci di booking** (`total`, `dp_amount`) saat dibuat.
- **Status booking dan status bayar dipisah.** `status` = siklus tamu, `payment_status` = kondisi uang (`unpaid → partial → paid → refunded`).
- Satu booking bisa punya banyak `payments` (DP, pelunasan, atau QR kadaluarsa lalu dibuat ulang).
- Teks tampilan tamu (`name`, `description`, fasilitas, kebijakan pembatalan) dua bahasa (`spatie/laravel-translatable`).
- Kredensial gateway/WA dan nomor rekening refund **terenkripsi** (`encrypted` cast), tidak boleh muncul di log.
- Soft delete untuk `villas`, `rooms`, `guests`, `tenants`.
- Index: `bookings(tenant_id, status, expires_at)`, `bookings(room_id, check_in, check_out)`, `payments(gateway_ref)`, `rooms(tenant_id, is_active)`.

### Menyusul (belum dibuat)
`booking_items` (multi-kamar), `coupons`, `reviews`, `ota_sync` (iCal), `activity_logs`, `notification_logs`.

---

## 3. Pembayaran oleh Tamu (full / DP, diatur owner)

- `payment_mode = full` : wajib bayar penuh.
- `payment_mode = dp` : bayar DP sebesar `dp_percent`.
- `payment_mode = both` : tamu memilih sendiri saat checkout.

Aturan DP:
1. DP diterima → booking `confirmed`, `payment_status = partial`, kamar terkunci.
2. Sisa tagihan jatuh tempo `balance_due_days_before_checkin` hari sebelum check-in. Dibayar lewat QRIS baru (`payments.type = balance`) atau dicatat manual owner (tunai/transfer).
3. Scheduler mengirim pengingat pelunasan. Jika lewat jatuh tempo, owner diberi tahu untuk menindaklanjuti atau membatalkan (tidak otomatis batal).

## 3b. Pembatalan & Refund (owner yang memutuskan)

```mermaid
flowchart TD
    A[Tamu klik Ajukan Pembatalan di halaman status booking] --> B[Isi alasan + rekening/e-wallet tujuan refund]
    B --> C[refunds: requested]
    C --> D[Notifikasi ke owner]
    D --> E{Owner putuskan}
    E -->|Tolak| F[refunds: rejected, booking tetap berjalan]
    E -->|Setujui + isi nominal refund| G[refunds: approved, booking cancelled, calendar_days dibebaskan]
    G --> H[Owner transfer manual ke rekening tamu]
    H --> I[Upload bukti transfer, refunds: refunded, payment_status: refunded]
    I --> J[Notifikasi ke tamu]
```

- Uang **tidak kembali otomatis** lewat QRIS. Owner transfer manual, sistem mencatat nominal, rekening tujuan, dan bukti transfer.
- Kebijakan pembatalan (mis. refund 100% jika H-7) ditulis owner di `cancellation_policy` dan ditampilkan saat checkout. Penentuan nominal tetap oleh owner.
- Owner juga bisa membatalkan booking sendiri dari admin (`requested_by = owner`).
- Refund otomatis via API gateway: opsional, menyusul jika gateway mendukung.

---

## 4. Status Booking

```mermaid
stateDiagram-v2
    [*] --> pending_payment : tamu buat booking
    pending_payment --> confirmed : pembayaran pertama (DP/penuh) sukses
    pending_payment --> expired : lewat expires_at
    pending_payment --> cancelled : tamu/owner batal
    confirmed --> checked_in : check-in
    confirmed --> cancelled : refund disetujui / owner batalkan
    checked_in --> completed : check-out
    expired --> [*]
    cancelled --> [*]
    completed --> [*]
```

---

## 5. Alur Booking + QRIS

```mermaid
sequenceDiagram
    actor Tamu
    participant Web as Situs publik (Livewire)
    participant DB as Database
    participant GW as Gateway milik tenant
    participant Q as Queue/Scheduler

    Tamu->>Web: Pilih villa/kamar, tanggal, jumlah tamu
    Web->>DB: Cek ketersediaan kamar + hitung harga
    Web-->>Tamu: Ringkasan harga (+ pilihan DP/penuh jika diizinkan)
    Tamu->>Web: Isi data diri, klik Pesan
    Web->>DB: BEGIN transaksi
    Web->>DB: Insert booking (pending_payment, expires_at +30 menit)
    Web->>DB: Insert calendar_days (UNIQUE room_id+date)
    alt tanggal sudah terpakai
        DB-->>Web: Duplicate key
        Web-->>Tamu: Tanggal tidak tersedia
    else sukses
        Web->>DB: COMMIT
        Web->>GW: Buat QRIS dinamis (nominal DP atau penuh)
        GW-->>Web: qr_string
        Web->>DB: Simpan payment (pending)
        Web-->>Tamu: Halaman bayar (QR + hitung mundur)
    end
    Tamu->>GW: Scan & bayar
    GW->>Web: Webhook /webhooks/payment/{gateway}/{tenant}
    Web->>DB: Catat log, verifikasi signature & nominal
    Web->>DB: Payment paid, booking confirmed, payment_status paid/partial
    Web->>Q: Notifikasi email/WhatsApp ke tamu & owner
    Web-->>Tamu: Status di halaman berubah otomatis
    Q->>DB: Tiap menit: booking pending lewat expires_at → expired, hapus calendar_days
```

---

## 6. Notifikasi WhatsApp

Kirim otomatis **tidak bisa hanya dengan nomor owner**: harus ada akun/API pengirim. Interface `WhatsAppSender` dengan driver per tenant (`tenant_settings.wa_driver`):

| Driver | Cara kerja | Catatan |
|---|---|---|
| `link` (default) | Tombol/link `wa.me` untuk tamu menghubungi owner; notifikasi ke owner lewat **email** | Gratis, tanpa API, tanpa risiko |
| `fonnte` (pihak ketiga) | Owner scan QR nomor WA-nya di dashboard Fonnte, sistem kirim lewat API Fonnte | Murah, **tidak resmi**, nomor berisiko diblokir; owner berlangganan Fonnte sendiri |
| `meta` | WhatsApp Business Cloud API resmi | Stabil, perlu verifikasi bisnis, pesan pakai template, ada biaya per percakapan |

Mulai dengan `link` + email, tambah driver `fonnte` di fase notifikasi.

---

## 7. Bahasa ID/EN

- `lang/id` dan `lang/en` untuk antarmuka (Livewire, email, Filament).
- Konten villa/kamar dua bahasa lewat kolom JSON.
- Pilihan bahasa disimpan di sesi (`/lang/id`, `/lang/en`); bahasa awal mengikuti `tenants.default_locale`. URL berprefix `/id` dan `/en` dipertimbangkan saat halaman publik dibuat (fase 3).
- `bookings.locale` menyimpan bahasa tamu supaya email/WA dikirim dalam bahasa yang sama.

---

## 8. Struktur Folder Laravel

```
app/
├── Enums/                  # BookingStatus, PaymentStatus, PaymentOption, TenantStatus, RefundStatus
├── Models/
│   └── Concerns/BelongsToTenant.php     # global scope tenant_id
├── Contracts/
│   ├── PaymentGateway.php               # createQris(), verifyWebhook(), parseWebhook()
│   └── WhatsAppSender.php
├── Services/
│   ├── AvailabilityService.php          # cek & kunci tanggal per kamar
│   ├── PricingService.php
│   ├── BookingService.php               # buat/batal/konfirmasi, hitung DP
│   ├── PaymentService.php
│   ├── RefundService.php                # ajukan, setujui, tandai refunded
│   ├── SubscriptionService.php          # invoice, suspend, cek kuota kamar
│   ├── Payment/ (IpaymuGateway, ManualGateway, FakeGateway, GatewayFactory)
│   └── Whatsapp/ (LinkSender, FonnteSender, MetaCloudSender)
├── Filament/
│   ├── Platform/            # panel super admin
│   └── Admin/               # panel owner (tenancy)
├── Livewire/Public/         # VillaList, VillaDetail, BookingForm, PayPage, BookingStatus, RefundRequestForm
├── Http/
│   ├── Controllers/Webhook/PaymentWebhookController.php
│   └── Middleware/ (ResolveTenant, SetLocale, EnsureTenantActive)
├── Jobs/                    # SendBookingNotification, ...
├── Console/Commands/ (ExpirePendingBookings, SendBalanceReminders, ProcessSubscriptions)
└── Notifications/
lang/ (id, en)
config/booking.php
database/ (migrations, seeders [PlanSeeder], factories)
tests/ (Feature, Unit)
```

---

## 9. Keamanan & Keandalan

- Verifikasi signature dan nominal webhook, simpan semua callback di `payment_webhook_logs`, proses idempotent.
- Global scope tenant + **test otomatis** bahwa tenant A tidak bisa melihat data tenant B.
- Perubahan booking + `calendar_days` dalam **DB transaction**.
- Rate limit endpoint booking, refund, dan webhook. Validasi tanggal, `min_nights`, `max_guests`.
- Kredensial dan nomor rekening terenkripsi, `.env` tidak masuk Git.
- Tenant `suspended` tidak bisa menerima booking baru, booking lama tetap berjalan.
- Backup database harian di produksi.

---

## 10. Fase Pengerjaan

| Fase | Isi | Hasil |
|---|---|---|
| 0 | Setup Laravel, Filament 2 panel, tenancy (scope, `ResolveTenant`), i18n dasar, `PlanSeeder` | Kerangka multi-tenant siap |
| 1 | Villa + kamar (CRUD, foto, fasilitas) dengan cek kuota paket | Owner bisa kelola villa & kamar |
| 2 | Ketersediaan, harga musiman, kalender, blok tanggal | Cek tanggal & harga akurat |
| 3 | Situs publik Livewire + booking + pembayaran manual + opsi DP | Alur pesan end-to-end (bisa didemokan) |
| 4 | Gateway QRIS per tenant + webhook + auto-expire + pelunasan DP | Pembayaran otomatis |
| 5 | Pembatalan & refund + notifikasi email/WhatsApp (`link`, lalu `fonnte`) | Kabar otomatis, refund tercatat |
| 6 | Langganan: invoice, suspend, panel platform | Model SaaS berjalan |
| 7 | Laporan, polishing, deploy (VPS, wildcard SSL) | Siap untuk client pertama |

Untuk client pertama, langganan bisa dicatat manual dulu (fase 6 menyusul) supaya cepat go-live.

---

## 11. Keputusan yang Sudah Diambil

- Multi-tenant SaaS, 1 akun owner bisa punya banyak villa; kuota paket dihitung dari total kamar.
- Paket: 10 kamar Rp150.000, 20 kamar Rp250.000, 30 kamar Rp350.000 (usulan), per bulan.
- Admin Filament, frontend Livewire, bahasa ID/EN.
- Pembayaran tamu: full/DP diatur owner; uang masuk ke gateway milik owner.
- Domain induk milik sendiri, tenant lewat subdomain.
- Refund: owner yang menyetujui dan transfer manual.
- Notifikasi: `link` + email dulu, lalu Fonnte.

## 12. Pertanyaan Terbuka

1. Harga paket di atas 30 kamar: lanjut +Rp100.000 per 10 kamar, atau harga khusus?
2. Ada masa **trial gratis** untuk owner baru? Berapa hari?
3. Langganan tahunan (diskon) diperlukan di awal?

---

## Catatan implementasi fase 4 (gateway QRIS)

- Kontrak `App\Contracts\PaymentGateway` (`createQris`, `verifyWebhook`, `parseWebhook`). Driver tersedia: `FakeGateway` (pengembangan/tes, tidak aktif di production). Driver gateway nyata (iPaymu/Duitku) ditambahkan di `App\Support\Payment\GatewayFactory` setelah ada akun sandbox untuk menguji format tanda tangan dan callback-nya.
- Webhook: `POST /webhooks/payment/{gateway}/{slug-tenant}`, di luar grup `web` (tanpa sesi/CSRF), dibatasi 120 permintaan/menit. Selalu dicatat di `payment_webhook_logs`; tanda tangan diverifikasi dengan kredensial tenant; nominal harus sama dengan tagihan; idempotent.
- Gateway gagal dihubungi -> tagihan tetap transfer manual. Pembayaran masuk setelah booking kedaluwarsa/batal -> dicatat `late_payment` (booking tidak dihidupkan), owner menindaklanjuti (refund di fase 5).
- Pelunasan DP lewat QRIS: tamu menekan "Bayar pelunasan" di halaman status booking; tagihan `balance` kedaluwarsa otomatis lewat `bookings:expire`.

---

## Catatan implementasi fase 5 (pembatalan, refund, notifikasi)

- **Tingkat refund per tenant** (`tenant_settings.refund_tiers`, mis. H-7 100%, H-3 50%): dipakai tingkat dengan H- terbesar yang terpenuhi; di luar semua tingkat 0%. `null` = bawaan (7/100, 3/50), `[]` = tanpa refund. Hanya **saran**: owner selalu bisa menetapkan nominal sendiri (0 sampai total yang sudah dibayar).
- **Alur**: tamu mengajukan (alasan + rekening tujuan, nomor rekening terenkripsi) -> owner setujui (booking dibatalkan, tanggal bebas) / tolak -> owner transfer manual lalu tandai selesai (bukti transfer). Refund penuh menandai pembayaran `refunded`. Owner juga bisa membatalkan booking sendiri sekaligus menetapkan refund.
- **Email** (`App\Support\Notifier`, `App\Mail\SimpleMail`): booking dibuat, pembayaran diterima/terkonfirmasi, dibatalkan, pengajuan refund (ke owner), keputusan refund, refund ditransfer, pengingat pelunasan (`bookings:remind-balance`, harian 09:00, sekali per booking). Bahasa mengikuti bahasa booking. Kegagalan kirim hanya dicatat di log dan tidak menggagalkan booking/pembayaran. Email owner memakai `tenant_settings.notify_email`.
- **SMTP produksi** (isi di `.env`, contoh Hostinger): `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME=<email@domainanda>`, `MAIL_PASSWORD=<sandi email>`, `MAIL_FROM_ADDRESS=<email@domainanda>`. Lokal: `MAIL_MAILER=log` (email ditulis ke `storage/logs/laravel.log`).
- WhatsApp otomatis (Fonnte/Meta) belum; saat ini tombol `wa.me` di halaman status booking.
