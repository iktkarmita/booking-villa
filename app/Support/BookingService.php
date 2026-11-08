<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingRuleViolated;
use App\Exceptions\DatesUnavailable;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\TenantSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alur booking: buat (kunci tanggal + tagihan pertama), konfirmasi pembayaran, pelunasan,
 * kedaluwarsa, pembatalan. Semua perubahan tanggal lewat Availability agar tidak double-booking.
 */
final class BookingService
{
    /**
     * @param  array{name:string,email:string,phone:string}  $guestData
     *
     * @throws BookingRuleViolated
     * @throws DatesUnavailable
     */
    public static function create(
        Room $room,
        string $checkIn,
        string $checkOut,
        int $guestsCount,
        array $guestData,
        ?string $option = null,
        string $locale = 'id',
        string $source = 'web',
        ?string $notes = null,
    ): Booking {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($in->lessThan(now()->startOfDay())) {
            throw new BookingRuleViolated('past_date');
        }

        $quote = PriceCalculator::quote($room, $in, $out);

        if (! $quote['meets_min_nights']) {
            throw new BookingRuleViolated('min_nights', ['min' => $quote['min_nights']]);
        }

        if ($guestsCount < 1 || $guestsCount > $room->max_guests) {
            throw new BookingRuleViolated('max_guests', ['max' => $room->max_guests]);
        }

        if (! Availability::isAvailable($room, $in, $out)) {
            throw new DatesUnavailable('Tanggal tidak tersedia.');
        }

        $settings = TenantSetting::query()->where('tenant_id', $room->tenant_id)->first();
        $mode = $settings?->payment_mode ?? 'full';
        $total = $quote['total'];

        $option = match ($mode) {
            'dp' => 'dp',
            'both' => in_array($option, ['full', 'dp'], true) ? $option : throw new BookingRuleViolated('payment_option'),
            default => 'full',
        };

        $dpAmount = null;
        $balanceDue = null;

        if ($option === 'dp') {
            $percent = max(1, min(99, (int) ($settings?->dp_percent ?? 30)));
            $dpAmount = (int) ceil($total * $percent / 100);

            if ($dpAmount >= $total) {
                $option = 'full';
                $dpAmount = null;
            } else {
                $days = (int) ($settings?->balance_due_days_before_checkin ?? 7);
                $balanceDue = CarbonImmutable::today()->max($in->subDays($days));
            }
        }

        $firstAmount = $option === 'dp' ? $dpAmount : $total;
        $expiresAt = now()->addMinutes((int) ($settings?->booking_hold_minutes ?? 120));

        $booking = DB::transaction(function () use ($room, $in, $out, $guestsCount, $guestData, $option, $locale, $source, $notes, $quote, $total, $dpAmount, $balanceDue, $firstAmount, $expiresAt) {
            $guest = Guest::withoutTenancy()
                ->where('tenant_id', $room->tenant_id)
                ->where('email', mb_strtolower(trim($guestData['email'])))
                ->first();

            $attributes = [
                'name' => trim($guestData['name']),
                'phone' => trim($guestData['phone']),
            ];

            if ($guest) {
                $guest->update($attributes);
            } else {
                $guest = Guest::create($attributes + [
                    'tenant_id' => $room->tenant_id,
                    'email' => mb_strtolower(trim($guestData['email'])),
                ]);
            }

            $booking = Booking::create([
                'tenant_id' => $room->tenant_id,
                'code' => self::newCode(),
                'room_id' => $room->getKey(),
                'guest_id' => $guest->getKey(),
                'check_in' => $in->toDateString(),
                'check_out' => $out->toDateString(),
                'nights' => $quote['nights'],
                'guests_count' => $guestsCount,
                'status' => BookingStatus::PendingPayment,
                'payment_status' => PaymentStatus::Unpaid,
                'payment_option' => $option,
                'total' => $total,
                'dp_amount' => $dpAmount,
                'paid_amount' => 0,
                'balance_due_date' => $balanceDue?->toDateString(),
                'locale' => $locale,
                'source' => $source,
                'expires_at' => $expiresAt,
                'notes' => $notes,
            ]);

            Availability::lock($room, $in, $out, 'booking', $booking->getKey());

            Payment::create([
                'tenant_id' => $room->tenant_id,
                'booking_id' => $booking->getKey(),
                'type' => $option === 'dp' ? 'dp' : 'full',
                'gateway' => 'manual',
                'method' => 'manual',
                'amount' => $firstAmount,
                'status' => 'pending',
                'expires_at' => $expiresAt,
            ]);

            return $booking;
        });

        // Panggilan ke gateway di luar transaksi; bila gagal, tamu tetap bisa transfer manual.
        $payment = Payment::withoutTenancy()->where('booking_id', $booking->getKey())->where('status', 'pending')->latest('id')->first();

        if ($payment) {
            PaymentService::startQris($payment);
        }

        Notifier::bookingCreated($booking);

        return $booking;
    }

    /** Owner mengonfirmasi pembayaran (transfer manual) sudah diterima. */
    public static function confirmPayment(Payment $payment): Booking
    {
        $changed = false;
        $first = false;

        $booking = DB::transaction(function () use ($payment, &$changed, &$first) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($payment->booking_id);
            $payment = Payment::withoutTenancy()->lockForUpdate()->findOrFail($payment->getKey());

            if ($payment->status === 'paid') {
                return $booking;
            }

            if (! in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
                throw new BookingRuleViolated('not_payable');
            }

            $payment->update(['status' => 'paid', 'paid_at' => now()]);

            $changed = true;
            $first = $booking->status === BookingStatus::PendingPayment;

            return self::applyPaid($booking, $payment->amount);
        });

        if ($changed) {
            Notifier::paymentReceived($booking, $payment->amount, $first);
        }

        return $booking;
    }

    /** Owner mencatat pelunasan/pembayaran tambahan yang diterima langsung (tunai/transfer). */
    public static function recordBalance(Booking $booking, int $amount, ?string $note = null): Booking
    {
        $booking = DB::transaction(function () use ($booking, $amount, $note) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($booking->getKey());

            if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
                throw new BookingRuleViolated('not_payable');
            }

            if ($amount < 1 || $amount > $booking->remaining()) {
                throw new BookingRuleViolated('overpay', ['max' => Money::idr($booking->remaining())]);
            }

            Payment::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->getKey(),
                'type' => 'balance',
                'gateway' => 'manual',
                'method' => 'manual',
                'amount' => $amount,
                'status' => 'paid',
                'paid_at' => now(),
                'note' => $note,
            ]);

            return self::applyPaid($booking, $amount);
        });

        Notifier::paymentReceived($booking, $amount, false);

        return $booking;
    }

    /** Booking pending yang lewat batas waktu -> expired, tanggal dibebaskan. */
    public static function expire(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($booking->getKey());

            if ($booking->status !== BookingStatus::PendingPayment) {
                return false;
            }

            $booking->update(['status' => BookingStatus::Expired]);
            Availability::release($booking->getKey());
            Payment::withoutTenancy()->where('booking_id', $booking->getKey())->where('status', 'pending')->update(['status' => 'expired']);

            return true;
        });
    }

    public static function expireOverdue(): int
    {
        $count = 0;

        Booking::withoutTenancy()
            ->where('status', BookingStatus::PendingPayment->value)
            ->where('expires_at', '<', now())
            ->each(function (Booking $booking) use (&$count) {
                $count += self::expire($booking) ? 1 : 0;
            });

        return $count;
    }

    /** Pembatalan oleh owner. Refund diproses terpisah (fase 5). */
    public static function cancel(Booking $booking, ?string $reason = null): Booking
    {
        $changed = false;

        $booking = DB::transaction(function () use ($booking, $reason, &$changed) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($booking->getKey());

            if (! $booking->status->holdsDates()) {
                return $booking;
            }

            $changed = true;

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            Availability::release($booking->getKey());
            Payment::withoutTenancy()->where('booking_id', $booking->getKey())->where('status', 'pending')->update(['status' => 'expired']);

            return $booking;
        });

        if ($changed) {
            Notifier::bookingCancelled($booking);
        }

        return $booking;
    }

    private static function applyPaid(Booking $booking, int $amount): Booking
    {
        $paid = $booking->paid_amount + $amount;

        $booking->update([
            'paid_amount' => $paid,
            'payment_status' => $paid >= $booking->total ? PaymentStatus::Paid : PaymentStatus::Partial,
            'status' => $booking->status === BookingStatus::PendingPayment ? BookingStatus::Confirmed : $booking->status,
            'expires_at' => null,
        ]);

        return $booking;
    }

    private static function newCode(): string
    {
        do {
            $code = 'BV-'.Str::upper(Str::random(8));
        } while (Booking::withoutTenancy()->where('code', $code)->exists());

        return $code;
    }
}
