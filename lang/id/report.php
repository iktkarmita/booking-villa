<?php

return [
    'nav' => 'Laporan',
    'title' => 'Laporan',
    'export' => 'Ekspor CSV',
    'from' => 'Dari tanggal',
    'to' => 'Sampai tanggal',
    'period' => 'Periode :from s/d :to (:days hari). Pendapatan dihitung dari pembayaran yang diterima pada periode ini.',
    'presets' => ['month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'last_30' => '30 hari terakhir', 'year' => 'Tahun ini'],
    'cards' => [
        'revenue' => 'Pembayaran diterima',
        'refunded' => 'Refund dibayarkan',
        'net' => 'Pendapatan bersih',
        'bookings' => 'Booking dibuat',
        'occupancy' => 'Okupansi',
        'nights' => 'Malam terjual / kapasitas',
    ],
    'by_status' => 'Booking per status',
    'by_villa' => 'Per villa',
    'cols' => ['villa' => 'Villa', 'bookings' => 'Booking', 'nights' => 'Malam', 'occupancy' => 'Okupansi', 'revenue' => 'Pendapatan'],
    'empty' => 'Belum ada data pada periode ini.',
];
