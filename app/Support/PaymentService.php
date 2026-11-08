<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRuleViolated;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Payment\GatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Pembayaran QRIS lewat gateway milik tenant: buat tagihan, tagih pelunasan, proses callback. */
final class PaymentService
{
    /**
     * Ubah pembayaran pending menjadi tagihan QRIS bila tenant memakai gateway.
     * Gagal menghubungi gateway -> tetap transfer manual (tamu tidak terblokir).
     */
    public static function startQris(Payment $payment): Payment
    {
        $settings = TenantSetting::query()->where('tenant_id', $payment->tenant_id)->first();
        $gateway = GatewayFactory::forSettings($settings);

        if ($gateway === null || $payment->status !== 'pending') {
            return $payment;
        }

        try {
            $booking = Booking::withoutTenancy()->findOrFail($payment->booking_id);
            $result = $gateway->createQris($payment, $booking, $settings->gateway_credentials ?? []);

            $payment->update([
                'gateway' => $gateway->name(),
                'method' => 'qris',
                'gateway_ref' => $result['ref'],
                'qr_string' => $result['qr_string'],
                'expires_at' => $result['expires_at'] ?? $payment->expires_at,
                'raw_response' => $result['raw'],
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return $payment->refresh();
    }

    /**
     * Tagihan pelunasan untuk sisa pembayaran booking yang sudah terkonfirmasi (DP).
     * Memakai ulang tagihan pending yang masih berlaku.
     *
     * @throws BookingRuleViolated
     */
    public static function requestBalance(Booking $booking): Payment
    {
        [$payment, $created] = DB::transaction(function () use ($booking) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($booking->getKey());

            if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true) || $booking->remaining() < 1) {
                throw new BookingRuleViolated('not_payable');
            }

            $existing = Payment::withoutTenancy()
                ->where('booking_id', $booking->getKey())
                ->where('type', 'balance')
                ->where('status', 'pending')
                ->where('method', 'qris')
                ->where('amount', $booking->remaining())
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('id')
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            // Tagihan lama yang tidak berlaku ditutup agar tidak ada dua tagihan pelunasan terbuka.
            Payment::withoutTenancy()
                ->where('booking_id', $booking->getKey())
                ->where('type', 'balance')
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            $settings = TenantSetting::query()->where('tenant_id', $booking->tenant_id)->first();

            return [Payment::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->getKey(),
                'type' => 'balance',
                'gateway' => 'manual',
                'method' => 'manual',
                'amount' => $booking->remaining(),
                'status' => 'pending',
                'expires_at' => now()->addMinutes((int) ($settings?->booking_hold_minutes ?? 120)),
            ]), true];
        });

        // Panggilan ke gateway di luar transaksi (tidak menahan kunci baris selama menunggu jaringan).
        return $created ? self::startQris($payment) : $payment;
    }

    /** Tagihan pelunasan yang lewat batas waktu ditutup. Tagihan booking baru ditangani BookingService::expire. */
    public static function expireStalePayments(): int
    {
        return Payment::withoutTenancy()
            ->where('status', 'pending')
            ->where('type', 'balance')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Proses callback gateway. Selalu dicatat; idempotent; tanda tangan dan nominal diverifikasi.
     *
     * @return array{status: int, result: string}
     */
    public static function handleWebhook(Tenant $tenant, string $gatewayName, Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        $log = PaymentWebhookLog::create([
            'tenant_id' => $tenant->getKey(),
            'gateway' => $gatewayName,
            'payload' => is_array($payload) ? $payload : ['raw' => mb_substr($request->getContent(), 0, 2000)],
        ]);

        $finish = function (string $result, int $status, ?Payment $payment = null) use ($log): array {
            $log->update([
                'result' => $result,
                'payment_id' => $payment?->getKey(),
                'processed_at' => now(),
            ]);

            return ['status' => $status, 'result' => $result];
        };

        $settings = TenantSetting::query()->where('tenant_id', $tenant->getKey())->first();

        if (($settings?->gateway ?? 'manual') !== $gatewayName || ! in_array($gatewayName, GatewayFactory::available(), true)) {
            return $finish('gateway_mismatch', 404);
        }

        $gateway = GatewayFactory::make($gatewayName);

        if (! $gateway->verifyWebhook($request, $settings->gateway_credentials ?? [])) {
            return $finish('invalid_signature', 401);
        }

        $log->update(['signature_valid' => true]);

        $event = $gateway->parseWebhook($request);

        $payment = $event['ref'] === '' ? null : Payment::withoutTenancy()
            ->where('tenant_id', $tenant->getKey())
            ->where('gateway', $gatewayName)
            ->where('gateway_ref', $event['ref'])
            ->first();

        if ($payment === null) {
            return $finish('unknown_payment', 404);
        }

        if ($event['status'] !== 'paid') {
            if (in_array($event['status'], ['failed', 'expired'], true) && $payment->status === 'pending') {
                $payment->update(['status' => $event['status']]);
            }

            return $finish('ignored', 200, $payment);
        }

        if ($payment->status === 'paid') {
            return $finish('duplicate', 200, $payment);
        }

        if ($event['amount'] !== $payment->amount) {
            return $finish('amount_mismatch', 422, $payment);
        }

        try {
            BookingService::confirmPayment($payment);
        } catch (BookingRuleViolated) {
            // Uang sudah diterima tetapi booking sudah kedaluwarsa/dibatalkan: catat, owner yang menindaklanjuti.
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'note' => 'Dibayar setelah booking tidak aktif - perlu tindakan owner (refund/aktifkan ulang).',
            ]);

            return $finish('late_payment', 200, $payment);
        }

        return $finish('processed', 200, $payment);
    }
}
