<?php

namespace App\Support;

use App\Exceptions\DatesUnavailable;
use App\Models\CalendarDay;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ketersediaan kamar. Satu baris calendar_days = satu malam terkunci.
 * Malam yang dicek/dikunci: dari check-in sampai SEBELUM check-out (hari check-out tetap bebas).
 */
final class Availability
{
    public static function isAvailable(Room $room, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): bool
    {
        if (! $room->is_active) {
            return false;
        }

        [$in, $out] = self::range($checkIn, $checkOut);

        return ! CalendarDay::withoutTenancy()
            ->where('room_id', $room->getKey())
            ->where('date', '>=', $in->toDateString())
            ->where('date', '<', $out->toDateString())
            ->exists();
    }

    /**
     * Kunci semua malam sekaligus (dipakai booking). Semua atau tidak sama sekali.
     *
     * @throws DatesUnavailable bila ada malam yang sudah terkunci
     */
    public static function lock(
        Room $room,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        string $type = CalendarDay::TYPE_BOOKING,
        ?int $bookingId = null,
        ?string $note = null,
    ): int {
        [$in, $out] = self::range($checkIn, $checkOut);

        try {
            return DB::transaction(function () use ($room, $in, $out, $type, $bookingId, $note) {
                $count = 0;

                for ($date = $in; $date->lessThan($out); $date = $date->addDay()) {
                    CalendarDay::create([
                        'tenant_id' => $room->tenant_id,
                        'room_id' => $room->getKey(),
                        'date' => $date->toDateString(),
                        'type' => $type,
                        'booking_id' => $bookingId,
                        'note' => $note,
                    ]);
                    $count++;
                }

                return $count;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new DatesUnavailable('Sebagian tanggal sudah terisi.', 0, $e);
        }
    }

    /** Lepas kunci milik satu booking (batal/expired). */
    public static function release(int $bookingId): int
    {
        return CalendarDay::withoutTenancy()->where('booking_id', $bookingId)->delete();
    }

    /**
     * Blokir tanggal oleh owner (rentang TERMASUK tanggal akhir). Hari yang sudah diblokir dilewati.
     *
     * @throws DatesUnavailable bila ada booking di rentang itu
     */
    public static function block(Room $room, CarbonInterface|string $from, CarbonInterface|string $until, ?string $note = null): int
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($until)->startOfDay();

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        return DB::transaction(function () use ($room, $start, $end, $note) {
            $hasBooking = CalendarDay::withoutTenancy()
                ->where('room_id', $room->getKey())
                ->where('type', CalendarDay::TYPE_BOOKING)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->exists();

            if ($hasBooking) {
                throw new DatesUnavailable('Ada booking pada rentang tanggal tersebut.');
            }

            $count = 0;

            for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                $day = CalendarDay::withoutTenancy()->firstOrCreate(
                    ['room_id' => $room->getKey(), 'date' => $date->toDateString()],
                    ['tenant_id' => $room->tenant_id, 'type' => CalendarDay::TYPE_BLOCK, 'note' => $note],
                );

                $count += $day->wasRecentlyCreated ? 1 : 0;
            }

            return $count;
        });
    }

    /** Buka blokir (rentang TERMASUK tanggal akhir). Booking tidak pernah ikut terhapus. */
    public static function unblock(Room $room, CarbonInterface|string $from, CarbonInterface|string $until): int
    {
        return CalendarDay::withoutTenancy()
            ->where('room_id', $room->getKey())
            ->where('type', CalendarDay::TYPE_BLOCK)
            ->whereBetween('date', [
                CarbonImmutable::parse($from)->toDateString(),
                CarbonImmutable::parse($until)->toDateString(),
            ])
            ->delete();
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private static function range(CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): array
    {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($out->lessThanOrEqualTo($in)) {
            throw new InvalidArgumentException('Tanggal check-out harus setelah check-in.');
        }

        return [$in, $out];
    }
}
