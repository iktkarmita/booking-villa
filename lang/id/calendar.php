<?php

return [
    'nav' => ['seasonal_prices' => 'Harga musiman', 'calendar' => 'Kalender'],
    'models' => ['seasonal_price' => 'Harga musiman'],
    'fields' => [
        'room' => 'Kamar',
        'label' => 'Nama musim',
        'start_date' => 'Mulai',
        'end_date' => 'Sampai (termasuk)',
        'price_per_night' => 'Harga per malam',
        'min_nights' => 'Min. malam',
        'min_nights_help' => 'Kosongkan untuk mengikuti min. malam kamar.',
        'note' => 'Catatan',
    ],
    'page' => [
        'title' => 'Kalender ketersediaan',
        'room' => 'Kamar',
        'no_rooms' => 'Belum ada kamar aktif. Tambahkan kamar terlebih dahulu.',
        'prev' => 'Bulan sebelumnya',
        'next' => 'Bulan berikutnya',
        'free' => 'Tersedia',
        'booked' => 'Terisi (booking)',
        'blocked' => 'Diblokir',
        'weekdays' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
    ],
    'actions' => [
        'block' => 'Blokir tanggal',
        'unblock' => 'Buka blokir',
        'blocked_done' => ':count malam diblokir.',
        'unblocked_done' => ':count malam dibuka kembali.',
        'conflict' => 'Ada booking pada rentang tanggal tersebut, tidak bisa diblokir.',
    ],
];
