<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /**
     * Buat QRIS dinamis untuk satu pembayaran.
     *
     * @param  array<string, mixed>  $credentials  kredensial milik tenant
     * @return array{ref: string, qr_string: string, expires_at: ?CarbonInterface, raw: array<string, mixed>}
     */
    public function createQris(Payment $payment, Booking $booking, array $credentials): array;

    /** Verifikasi tanda tangan callback dengan kredensial tenant. */
    public function verifyWebhook(Request $request, array $credentials): bool;

    /**
     * Terjemahkan callback ke bentuk umum.
     *
     * @return array{ref: string, status: 'paid'|'failed'|'expired'|'pending', amount: int}
     */
    public function parseWebhook(Request $request): array;
}
