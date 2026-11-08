<?php

return [

    /*
    | Domain induk platform. Situs tiap tenant = {subdomain}.{base_domain}.
    | Lokal: "localhost" (browser modern otomatis mengarahkan *.localhost ke 127.0.0.1).
    */
    'base_domain' => env('APP_BASE_DOMAIN', 'localhost'),

    /*
    | Host lain yang dianggap "pusat" (bukan tenant), dipisah koma.
    */
    'central_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BOOKING_CENTRAL_HOSTS', '127.0.0.1'))
    ))),

    /*
    | Subdomain yang tidak boleh dipakai tenant dan diperlakukan sebagai pusat.
    */
    'reserved_subdomains' => ['www', 'app', 'platform', 'admin'],

    'locales' => ['id', 'en'],
    'default_locale' => env('APP_LOCALE', 'id'),
    // Bahasa panel (owner/platform) bila belum memilih; panel owner memakai bahasa default tenant.
    'panel_locale' => env('BOOKING_PANEL_LOCALE', 'id'),

    /*
    | Akun super admin yang dibuat SetupSeeder.
    | Di luar environment "local", BOOKING_ADMIN_PASSWORD wajib diisi.
    */
    'admin_email' => env('BOOKING_ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('BOOKING_ADMIN_PASSWORD'),

    /*
    | Langganan tenant (tagihan ke owner, dibayar ke rekening platform).
    */
    'subscription' => [
        // Tagihan periode berikutnya dibuat sekian hari sebelum langganan berakhir.
        'invoice_lead_days' => (int) env('BOOKING_INVOICE_LEAD_DAYS', 7),
        // Masa tenggang setelah langganan berakhir sebelum tenant ditangguhkan.
        'grace_days' => (int) env('BOOKING_GRACE_DAYS', 7),
        // Pengingat dikirim sekian hari sebelum jatuh tempo (sekali per tagihan).
        'reminder_days' => (int) env('BOOKING_REMINDER_DAYS', 3),
    ],

    /*
    | Rekening platform untuk pembayaran tagihan langganan (ditampilkan ke owner).
    */
    'platform_bank' => [
        'name' => env('BOOKING_PLATFORM_BANK_NAME'),
        'number' => env('BOOKING_PLATFORM_BANK_NUMBER'),
        'holder' => env('BOOKING_PLATFORM_BANK_HOLDER'),
    ],

];
