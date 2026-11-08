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

    /*
    | Akun super admin yang dibuat SetupSeeder.
    | Di luar environment "local", BOOKING_ADMIN_PASSWORD wajib diisi.
    */
    'admin_email' => env('BOOKING_ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('BOOKING_ADMIN_PASSWORD'),

];
