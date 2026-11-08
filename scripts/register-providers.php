<?php

/*
| Mendaftarkan BookingServiceProvider ke bootstrap/providers.php (Laravel 11+).
| Aman dijalankan berulang.
*/

$file = __DIR__.'/../bootstrap/providers.php';

if (! is_file($file)) {
    fwrite(STDERR, "bootstrap/providers.php tidak ditemukan.\n");
    exit(1);
}

$content = file_get_contents($file);

if (str_contains($content, 'BookingServiceProvider')) {
    echo "BookingServiceProvider sudah terdaftar.\n";
    exit(0);
}

$needle = 'App\\Providers\\AppServiceProvider::class,';

if (! str_contains($content, $needle)) {
    fwrite(STDERR, "Pola AppServiceProvider tidak ditemukan. Tambahkan manual ke bootstrap/providers.php:\n");
    fwrite(STDERR, "    App\\Providers\\BookingServiceProvider::class,\n");
    exit(1);
}

file_put_contents($file, str_replace(
    $needle,
    $needle."\n    App\\Providers\\BookingServiceProvider::class,",
    $content
));

echo "BookingServiceProvider didaftarkan.\n";
