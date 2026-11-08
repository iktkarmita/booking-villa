<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gateway tiruan untuk pengembangan/tes. Callback ditandatangani HMAC-SHA256 isi body
 * memakai "secret" tenant, dikirim di header X-Signature. Tidak tersedia di production.
 */
final class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createQris(Payment $payment, Booking $booking, array $credentials): array
    {
        $ref = 'FAKE-'.Str::upper(Str::random(12));

        return [
            'ref' => $ref,
            'qr_string' => 'FAKEQRIS|'.$ref.'|'.$payment->amount,
            'expires_at' => $payment->expires_at,
            'raw' => ['driver' => 'fake'],
        ];
    }

    public function verifyWebhook(Request $request, array $credentials): bool
    {
        $secret = (string) ($credentials['secret'] ?? '');
        $signature = (string) $request->header('X-Signature', '');

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($request->getContent(), $secret), $signature);
    }

    public function parseWebhook(Request $request): array
    {
        $status = (string) $request->input('status', 'pending');

        return [
            'ref' => (string) $request->input('ref', ''),
            'status' => in_array($status, ['paid', 'failed', 'expired'], true) ? $status : 'pending',
            'amount' => (int) $request->input('amount', 0),
        ];
    }

    public static function sign(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }
}
