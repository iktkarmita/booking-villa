<?php

namespace App\Support;

use App\Mail\SimpleMail;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email notifikasi ke tamu dan owner. Kegagalan kirim tidak pernah menggagalkan booking/pembayaran:
 * dicatat ke log lalu diabaikan. Teks mengikuti bahasa booking (tamu) atau bahasa default (owner).
 */
final class Notifier
{
    public static function bookingCreated(Booking $booking): void
    {
        self::toGuest($booking, 'created', ['amount' => Money::idr(self::firstAmount($booking)), 'deadline' => self::time($booking->expires_at)]);
        self::toOwner($booking, 'owner_created');
    }

    public static function paymentReceived(Booking $booking, int $amount, bool $firstConfirmation): void
    {
        $params = ['amount' => Money::idr($amount)];

        self::toGuest($booking, $firstConfirmation ? 'confirmed' : 'paid_more', $params);
        self::toOwner($booking, 'owner_paid', $params);
    }

    public static function bookingCancelled(Booking $booking): void
    {
        self::toGuest($booking, 'cancelled');
    }

    public static function refundRequested(Refund $refund): void
    {
        $booking = self::booking($refund);

        self::toOwner($booking, 'refund_requested', [
            'suggested' => Money::idr($refund->suggested_amount),
            'reason' => (string) $refund->reason,
        ], panel: true);
    }

    public static function refundApproved(Refund $refund): void
    {
        $amount = (int) $refund->amount_approved;

        self::toGuest(self::booking($refund), $amount > 0 ? 'refund_approved' : 'refund_approved_none', [
            'amount' => Money::idr($amount),
            'note' => (string) $refund->owner_note,
        ]);
    }

    public static function refundRejected(Refund $refund): void
    {
        self::toGuest(self::booking($refund), 'refund_rejected', ['note' => (string) $refund->owner_note]);
    }

    public static function refundPaid(Refund $refund): void
    {
        self::toGuest(self::booking($refund), 'refund_paid', ['amount' => Money::idr((int) $refund->amount_approved)]);
    }

    public static function balanceReminder(Booking $booking): void
    {
        self::toGuest($booking, 'balance_reminder', [
            'remaining' => Money::idr($booking->remaining()),
            'due' => $booking->balance_due_date?->format('d/m/Y') ?? '-',
        ]);
    }

    private static function booking(Refund $refund): Booking
    {
        return Booking::withoutTenancy()->findOrFail($refund->booking_id);
    }

    private static function firstAmount(Booking $booking): int
    {
        return (int) ($booking->payment_option === 'dp' ? $booking->dp_amount : $booking->total);
    }

    private static function time($value): string
    {
        return $value ? $value->timezone(config('app.timezone'))->format('d/m/Y H:i') : '-';
    }

    private static function toGuest(Booking $booking, string $key, array $extra = []): void
    {
        $email = $booking->guest()->withoutGlobalScopes()->value('email');

        if (blank($email)) {
            return;
        }

        self::send($booking, $email, $booking->locale ?: config('booking.default_locale', 'id'), $key, $extra, panel: false);
    }

    private static function toOwner(Booking $booking, string $key, array $extra = [], bool $panel = false): void
    {
        $email = TenantSetting::query()->where('tenant_id', $booking->tenant_id)->value('notify_email');

        if (blank($email)) {
            return;
        }

        self::send($booking, $email, config('booking.default_locale', 'id'), $key, $extra, panel: true);
    }

    private static function send(Booking $booking, string $to, string $locale, string $key, array $extra, bool $panel): void
    {
        try {
            $booking = Booking::withoutTenancy()->with(['room.villa', 'guest'])->findOrFail($booking->getKey());
            $tenant = \App\Models\Tenant::query()->findOrFail($booking->tenant_id);

            App::withLocale($locale, function () use ($booking, $tenant, $to, $key, $extra, $panel) {
                $params = $extra + [
                    'tenant' => $tenant->name,
                    'code' => $booking->code,
                    'name' => (string) $booking->guest?->name,
                    'room' => $booking->room?->villa?->localized('name').' - '.$booking->room?->localized('name'),
                    'dates' => $booking->check_in->format('d/m/Y').' - '.$booking->check_out->format('d/m/Y'),
                    'total' => Money::idr($booking->total),
                    'remaining' => Money::idr($booking->remaining()),
                ];

                $url = $panel ? self::siteBase($tenant).'/admin/'.$tenant->slug.'/bookings' : self::siteBase($tenant).'/booking/'.$booking->code;

                Mail::to($to)->send(new SimpleMail(
                    __('mail.'.$key.'.subject', $params),
                    __('mail.'.$key.'.body', $params),
                    $url,
                    __($panel ? 'mail.open_panel' : 'mail.open_booking'),
                    $tenant->name,
                ));
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Alamat dasar situs tenant (domain sendiri, atau subdomain platform). Port ikut APP_URL (mis. :8000 di lokal). */
    private static function siteBase(\App\Models\Tenant $tenant): string
    {
        $app = parse_url((string) config('app.url'));
        $scheme = $app['scheme'] ?? 'https';
        $port = isset($app['port']) ? ':'.$app['port'] : '';
        $host = filled($tenant->custom_domain) ? $tenant->custom_domain : $tenant->subdomain.'.'.config('booking.base_domain');

        return $scheme.'://'.$host.$port;
    }
}
