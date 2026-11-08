<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\TenantSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Tingkat refund per tenant: pilih tingkat dengan "H-" terbesar yang masih terpenuhi. */
final class RefundPolicy
{
    public const DEFAULT_TIERS = [['days' => 7, 'percent' => 100], ['days' => 3, 'percent' => 50]];

    /** @return list<array{days: int, percent: int}> urut dari H- terbesar */
    public static function tiers(?TenantSetting $settings): array
    {
        $tiers = $settings?->refund_tiers ?? self::DEFAULT_TIERS; // null = bawaan, [] = tanpa refund

        $tiers = collect($tiers)
            ->filter(fn ($t) => is_array($t) && isset($t['days'], $t['percent']))
            ->map(fn ($t) => ['days' => max(0, (int) $t['days']), 'percent' => max(0, min(100, (int) $t['percent']))])
            ->sortByDesc('days')
            ->values()
            ->all();

        return $tiers;
    }

    public static function percent(?TenantSetting $settings, CarbonInterface $checkIn, ?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $daysBefore = (int) $today->diffInDays(CarbonImmutable::parse($checkIn)->startOfDay(), false);

        foreach (self::tiers($settings) as $tier) {
            if ($daysBefore >= $tier['days']) {
                return $tier['percent'];
            }
        }

        return 0;
    }

    /** Refund yang disarankan kebijakan: persentase dari yang sudah dibayar (dibulatkan ke bawah). */
    public static function suggestedAmount(Booking $booking): int
    {
        $settings = TenantSetting::query()->where('tenant_id', $booking->tenant_id)->first();

        return (int) floor($booking->paid_amount * self::percent($settings, $booking->check_in) / 100);
    }
}
