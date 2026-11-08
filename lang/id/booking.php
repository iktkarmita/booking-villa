<?php

return [
    'welcome' => 'Selamat datang',
    'tenant_suspended' => 'Layanan untuk villa ini sedang dinonaktifkan sementara.',

    'nav' => [
        'tenants' => 'Tenant',
        'plans' => 'Paket',
        'subscriptions' => 'Langganan',
        'users' => 'Pengguna',
    ],

    'models' => [
        'tenant' => 'Tenant',
        'plan' => 'Paket',
        'subscription' => 'Langganan',
        'user' => 'Pengguna',
    ],

    'fields' => [
        'name' => 'Nama',
        'slug' => 'Slug',
        'subdomain' => 'Subdomain',
        'custom_domain' => 'Domain sendiri',
        'status' => 'Status',
        'default_locale' => 'Bahasa default',
        'timezone' => 'Zona waktu',
        'currency' => 'Mata uang',
        'price' => 'Harga',
        'max_rooms' => 'Maksimal kamar',
        'interval' => 'Periode',
        'is_active' => 'Aktif',
        'tenant' => 'Tenant',
        'tenants' => 'Tenant',
        'plan' => 'Paket',
        'starts_at' => 'Mulai',
        'ends_at' => 'Berakhir',
        'grace_until' => 'Masa tenggang sampai',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'is_super_admin' => 'Super admin',
    ],

    'help' => [
        'subdomain' => 'Alamat situs: {subdomain}.domain-induk. Kosongkan untuk memakai slug.',
    ],

    'tenant_status' => [
        'trial' => 'Percobaan',
        'active' => 'Aktif',
        'suspended' => 'Ditangguhkan',
    ],

    'subscription_status' => [
        'trial' => 'Percobaan',
        'active' => 'Aktif',
        'past_due' => 'Menunggak',
        'canceled' => 'Dibatalkan',
    ],

    'intervals' => [
        'month' => 'Bulanan',
        'year' => 'Tahunan',
    ],

    'widgets' => [
        'plan' => 'Paket',
        'room_limit' => 'Batas kamar',
        'valid_until' => 'Berlaku sampai',
    ],
];
