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

$entry = '    App\\Providers\\BookingServiceProvider::class,';
$pos = strrpos($content, '];');

if ($pos === false) {
    fwrite(STDERR, "Format providers.php tidak dikenali. Tambahkan manual ke bootstrap/providers.php:\n");
    fwrite(STDERR, "    App\\Providers\\BookingServiceProvider::class,\n");
    exit(1);
}

$content = rtrim(substr($content, 0, $pos))."\n".$entry."\n".substr($content, $pos);
file_put_contents($file, $content);

echo "BookingServiceProvider didaftarkan.\n";
