<?php

return [
    'nav' => ['villas' => 'Villa', 'rooms' => 'Kamar'],
    'models' => ['villa' => 'Villa', 'room' => 'Kamar'],
    'fields' => [
        'name' => 'Nama',
        'name_id' => 'Nama (Indonesia)',
        'name_en' => 'Nama (Inggris)',
        'description_id' => 'Deskripsi (Indonesia)',
        'description_en' => 'Deskripsi (Inggris)',
        'address' => 'Alamat',
        'check_in_time' => 'Jam check-in',
        'check_out_time' => 'Jam check-out',
        'is_active' => 'Aktif',
        'amenities' => 'Fasilitas',
        'images' => 'Foto',
        'image' => 'Foto',
        'villa' => 'Villa',
        'bedrooms' => 'Kamar tidur',
        'bathrooms' => 'Kamar mandi',
        'max_guests' => 'Maks. tamu',
        'base_price' => 'Harga per malam',
        'min_nights' => 'Min. malam',
        'rooms_count' => 'Jumlah kamar',
    ],
    'sections' => ['info' => 'Informasi villa', 'photos' => 'Foto villa', 'amenities' => 'Fasilitas'],
    'quota' => [
        'exceeded' => 'Kuota kamar aktif paket Anda (:limit kamar) sudah penuh. Nonaktifkan kamar lain atau tingkatkan paket.',
        'helper' => 'Sisa kuota: :remaining dari :limit kamar aktif.',
    ],
];
