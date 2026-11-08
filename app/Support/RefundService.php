<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingRuleViolated;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan dengan refund manual: tamu mengajukan -> owner setujui (tentukan nominal) / tolak
 * -> owner transfer manual lalu tandai selesai. Uang tidak kembali otomatis lewat QRIS.
 */
final class RefundService
{
    /**
     * Pengajuan pembatalan oleh tamu (booking terkonfirmasi yang sudah ada pembayarannya).
     *
     * @param  array{bank?: ?string, name?: ?string, number?: ?string}  $dest
     *
     * @throws BookingRuleViolated
     */
    public static function request(Booking $booking, string $reason, array $dest): Refund
    {
        $refund = DB::transaction(function () use ($booking, $reason, $dest) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($booking->getKey());

            if ($booking->status !== BookingStatus::Confirmed || $booking->paid_amount < 1) {
                throw new BookingRuleViolated('refund_not_allowed');
            }

            $open = Refund::withoutTenancy()->where('booking_id', $booking->getKey())->whereIn('status', ['requested', 'approved'])->exists();

            if ($open) {
                throw new BookingRuleViolated('refund_exists');
            }

            return Refund::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->getKey(),
                'requested_by' => 'guest',
                'reason' => $reason,
                'status' => 'requested',
                'suggested_amount' => RefundPolicy::suggestedAmount($booking),
                'dest_bank' => $dest['bank'] ?? null,
                'dest_account_name' => $dest['name'] ?? null,
                'dest_account_number' => $dest['number'] ?? null,
            ]);
        });

        Notifier::refundRequested($refund);

        return $refund;
    }

    /**
     * Owner menyetujui: booking dibatalkan (tanggal dibebaskan) dan nominal refund ditetapkan.
     * Nominal 0 berarti tidak ada uang yang dikembalikan, refund langsung selesai.
     *
     * @throws BookingRuleViolated
     */
    public static function approve(Refund $refund, int $amount, ?string $note = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $amount, $note) {
            $refund = Refund::withoutTenancy()->lockForUpdate()->findOrFail($refund->getKey());
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($refund->booking_id);

            if ($refund->status !== 'requested') {
                throw new BookingRuleViolated('refund_not_pending');
            }

            if ($amount < 0 || $amount > $booking->paid_amount) {
                throw new BookingRuleViolated('refund_amount', ['max' => Money::idr($booking->paid_amount)]);
            }

            BookingService::cancel($booking, $refund->reason);

            $refund->update([
                'status' => $amount === 0 ? 'refunded' : 'approved',
                'amount_approved' => $amount,
                'owner_note' => $note,
                'decided_at' => now(),
                'refunded_at' => $amount === 0 ? now() : null,
            ]);

            return $refund;
        });

        Notifier::refundApproved($refund);

        return $refund;
    }

    /** @throws BookingRuleViolated */
    public static function reject(Refund $refund, ?string $note = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $note) {
            $refund = Refund::withoutTenancy()->lockForUpdate()->findOrFail($refund->getKey());

            if ($refund->status !== 'requested') {
                throw new BookingRuleViolated('refund_not_pending');
            }

            $refund->update(['status' => 'rejected', 'owner_note' => $note, 'decided_at' => now()]);

            return $refund;
        });

        Notifier::refundRejected($refund);

        return $refund;
    }

    /**
     * Owner sudah mentransfer refund. Refund penuh menandai pembayaran booking "dikembalikan".
     *
     * @throws BookingRuleViolated
     */
    public static function markRefunded(Refund $refund, ?string $proofPath = null, ?string $note = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $proofPath, $note) {
            $refund = Refund::withoutTenancy()->lockForUpdate()->findOrFail($refund->getKey());

            if ($refund->status !== 'approved') {
                throw new BookingRuleViolated('refund_not_pending');
            }

            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($refund->booking_id);

            $refund->update([
                'status' => 'refunded',
                'proof_path' => $proofPath ?? $refund->proof_path,
                'owner_note' => $note ?? $refund->owner_note,
                'refunded_at' => now(),
            ]);

            if ($refund->amount_approved >= $booking->paid_amount && $booking->paid_amount > 0) {
                $booking->update(['payment_status' => PaymentStatus::Refunded]);
                Payment::withoutTenancy()->where('booking_id', $booking->getKey())->where('status', 'paid')->update(['status' => 'refunded']);
            }

            return $refund;
        });

        Notifier::refundPaid($refund);

        return $refund;
    }

    /**
     * Owner membatalkan booking sendiri, sekaligus menetapkan refund (0 = tanpa refund).
     * Refund yang dibuat berstatus "approved": owner tinggal mentransfer lalu menandainya selesai.
     *
     * @throws BookingRuleViolated
     */
    public static function ownerCancel(Booking $booking, ?string $reason, int $refundAmount): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $refundAmount) {
            $booking = Booking::withoutTenancy()->lockForUpdate()->findOrFail($booking->getKey());

            if (! $booking->status->holdsDates()) {
                throw new BookingRuleViolated('not_payable');
            }

            if ($refundAmount < 0 || $refundAmount > $booking->paid_amount) {
                throw new BookingRuleViolated('refund_amount', ['max' => Money::idr($booking->paid_amount)]);
            }

            $suggested = RefundPolicy::suggestedAmount($booking);

            $booking = BookingService::cancel($booking, $reason);

            if ($refundAmount > 0) {
                Refund::create([
                    'tenant_id' => $booking->tenant_id,
                    'booking_id' => $booking->getKey(),
                    'requested_by' => 'owner',
                    'reason' => $reason,
                    'status' => 'approved',
                    'suggested_amount' => $suggested,
                    'amount_approved' => $refundAmount,
                    'decided_at' => now(),
                ]);
            }

            return $booking;
        });
    }
}
