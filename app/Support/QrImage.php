<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

/** Mengubah string QRIS menjadi gambar SVG (data URI). Null bila pustaka QR tidak tersedia. */
final class QrImage
{
    public static function dataUri(string $payload): ?string
    {
        if (! class_exists(QRCode::class)) {
            return null;
        }

        try {
            return (new QRCode(new QROptions([
                'outputType' => QROutputInterface::MARKUP_SVG,
                'outputBase64' => true,
                'addQuietzone' => true,
            ])))->render($payload);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
