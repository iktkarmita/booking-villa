<?php

namespace App\Support;

use App\Models\Room;
use App\Models\SeasonalPrice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Menghitung harga menginap per malam.
 * Aturan: harga dasar kamar, ditimpa harga musiman bila malam itu masuk rentang musim.
 * Jika beberapa musim tumpang tindih, musim yang dibuat paling akhir (id terbesar) menang.
 * Tanggal check-out tidak dihitung sebagai malam.
 */
final class PriceCalculator
{
    /**
     * @return array{nights:int, nightly:array<string,int>, total:int, min_nights:int, meets_min_nights:bool}
     */
    public static function quote(Room $room, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): array
    {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($out->lessThanOrEqualTo($in)) {
            throw new InvalidArgumentException('Tanggal check-out harus setelah check-in.');
        }

        $seasons = SeasonalPrice::withoutTenancy()
            ->where('room_id', $room->getKey())
            ->where('start_date', '<=', $out->subDay()->toDateString())
            ->where('end_date', '>=', $in->toDateString())
            ->orderByDesc('id')
            ->get();

        $nightly = [];
        $minNights = (int) $room->min_nights;

        for ($date = $in; $date->lessThan($out); $date = $date->addDay()) {
            $day = $date->toDateString();

            $season = $seasons->first(
                fn (SeasonalPrice $s) => $s->start_date->toDateString() <= $day && $s->end_date->toDateString() >= $day
            );

            $nightly[$day] = (int) ($season?->price_per_night ?? $room->base_price);

            if ($season?->min_nights) {
                $minNights = max($minNights, (int) $season->min_nights);
            }
        }

        $nights = count($nightly);

        return [
            'nights' => $nights,
            'nightly' => $nightly,
            'total' => array_sum($nightly),
            'min_nights' => $minNights,
            'meets_min_nights' => $nights >= $minNights,
        ];
    }

    /** Harga satu malam tertentu (untuk tampilan kalender). */
    public static function priceForNight(Room $room, CarbonInterface|string $date): int
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return self::quote($room, $day, $day->addDay())['total'];
    }
}
