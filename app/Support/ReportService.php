<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Room;
use App\Models\Villa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Laporan untuk owner (tenant aktif): pendapatan, okupansi, dan booking per periode.
 * Semua query lewat model ber-scope tenant, jadi data tenant lain tidak pernah ikut terhitung.
 */
final class ReportService
{
    /**
     * @return array{
     *   from: string, to: string, days: int,
     *   revenue: int, refunded: int, net: int,
     *   bookings: array<string,int>, bookings_total: int,
     *   nights_sold: int, nights_capacity: int, occupancy: float,
     *   villas: list<array{villa_id:int, name:string, bookings:int, nights:int, capacity:int, occupancy:float, revenue:int}>
     * }
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $days = (int) $from->diffInDays($to) + 1;

        $revenue = (int) Payment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to->endOfDay()])
            ->sum('amount');

        $refunded = (int) Refund::query()
            ->where('status', 'refunded')
            ->whereBetween('refunded_at', [$from, $to->endOfDay()])
            ->sum('amount_approved');

        // Booking yang dibuat pada periode, dikelompokkan per status.
        $bookings = Booking::query()
            ->whereBetween('created_at', [$from, $to->endOfDay()])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        // Malam terjual = baris calendar_days bertipe booking di dalam periode (bukan blokir).
        $nightsByVilla = CalendarDay::query()
            ->where('calendar_days.type', CalendarDay::TYPE_BOOKING)
            ->whereBetween('calendar_days.date', [$from->toDateString(), $to->toDateString()])
            ->join('rooms', 'rooms.id', '=', 'calendar_days.room_id')
            ->select('rooms.villa_id', DB::raw('count(*) as nights'))
            ->groupBy('rooms.villa_id')
            ->pluck('nights', 'villa_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        $roomsByVilla = Room::query()
            ->where('is_active', true)
            ->select('villa_id', DB::raw('count(*) as total'))
            ->groupBy('villa_id')
            ->pluck('total', 'villa_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        $revenueByVilla = Payment::query()
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [$from, $to->endOfDay()])
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->join('rooms', 'rooms.id', '=', 'bookings.room_id')
            ->select('rooms.villa_id', DB::raw('sum(payments.amount) as total'))
            ->groupBy('rooms.villa_id')
            ->pluck('total', 'villa_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        $bookingsByVilla = Booking::query()
            ->whereBetween('bookings.created_at', [$from, $to->endOfDay()])
            ->whereIn('bookings.status', ['confirmed', 'checked_in', 'completed'])
            ->join('rooms', 'rooms.id', '=', 'bookings.room_id')
            ->select('rooms.villa_id', DB::raw('count(*) as total'))
            ->groupBy('rooms.villa_id')
            ->pluck('total', 'villa_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        $villaIds = array_unique([
            ...array_keys($nightsByVilla),
            ...array_keys($roomsByVilla),
            ...array_keys($revenueByVilla),
            ...array_keys($bookingsByVilla),
        ]);

        $names = Villa::query()->withTrashed()->whereIn('id', $villaIds)->get()
            ->mapWithKeys(fn (Villa $villa) => [$villa->id => (string) $villa->localized('name')]);

        $villas = collect($villaIds)->map(function (int $id) use ($names, $nightsByVilla, $roomsByVilla, $revenueByVilla, $bookingsByVilla, $days) {
            $capacity = ($roomsByVilla[$id] ?? 0) * $days;
            $nights = $nightsByVilla[$id] ?? 0;

            return [
                'villa_id' => $id,
                'name' => $names[$id] ?? '-',
                'bookings' => $bookingsByVilla[$id] ?? 0,
                'nights' => $nights,
                'capacity' => $capacity,
                'occupancy' => self::percent($nights, $capacity),
                'revenue' => $revenueByVilla[$id] ?? 0,
            ];
        })->sortByDesc('revenue')->values()->all();

        $nightsSold = array_sum($nightsByVilla);
        $capacity = array_sum($roomsByVilla) * $days;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'revenue' => $revenue,
            'refunded' => $refunded,
            'net' => $revenue - $refunded,
            'bookings' => $bookings,
            'bookings_total' => array_sum($bookings),
            'nights_sold' => $nightsSold,
            'nights_capacity' => $capacity,
            'occupancy' => self::percent($nightsSold, $capacity),
            'villas' => $villas,
        ];
    }

    /** Daftar booking pada periode (berdasarkan tanggal check-in) untuk diekspor. */
    public static function bookingRows(CarbonImmutable $from, CarbonImmutable $to): \Generator
    {
        $query = Booking::query()
            ->with(['room.villa', 'guest'])
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->orderBy('check_in')
            ->orderBy('id');

        foreach ($query->cursor() as $booking) {
            yield [
                'code' => $booking->code,
                'villa' => (string) $booking->room?->villa?->localized('name'),
                'room' => (string) $booking->room?->localized('name'),
                'guest' => $booking->guest?->name,
                'phone' => $booking->guest?->phone,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'nights' => $booking->nights,
                'status' => $booking->status->value,
                'payment_status' => $booking->payment_status->value,
                'total' => $booking->total,
                'paid' => $booking->paid_amount,
            ];
        }
    }

    /** CSV (UTF-8 dengan BOM supaya Excel membaca benar) dengan penangkal formula injection. */
    public static function csv(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'code', 'villa', 'room', 'guest', 'phone', 'check_in', 'check_out',
            'nights', 'status', 'payment_status', 'total', 'paid',
        ], ',', '"', '\\');

        foreach (self::bookingRows($from, $to) as $row) {
            fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '\\');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Sel teks yang diawali = + - @ dianggap rumus oleh Excel; beri awalan apostrof. */
    public static function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private static function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
