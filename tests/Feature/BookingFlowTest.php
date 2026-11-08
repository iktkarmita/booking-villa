<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingRuleViolated;
use App\Exceptions\DatesUnavailable;
use App\Models\Booking;
use App\Models\CalendarDay;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Availability;
use App\Support\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Room $room;

    private string $in;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);
        $this->room = Room::factory()->create([
            'tenant_id' => $this->tenant->id,
            'base_price' => 500000,
            'max_guests' => 4,
            'min_nights' => 1,
        ]);

        $this->in = CarbonImmutable::today()->addDays(30)->toDateString();
        $this->out = CarbonImmutable::today()->addDays(33)->toDateString();
    }

    private function settings(array $values): void
    {
        $this->tenant->settings()->update($values);
    }

    private function book(?string $option = null, string $email = 'tamu@example.com', ?string $in = null, ?string $out = null, int $guests = 2): Booking
    {
        return BookingService::create(
            $this->room->fresh(),
            $in ?? $this->in,
            $out ?? $this->out,
            $guests,
            ['name' => 'Budi', 'email' => $email, 'phone' => '08123456789'],
            $option,
        );
    }

    public function test_booking_penuh_mengunci_tanggal_dan_membuat_tagihan(): void
    {
        $booking = $this->book();

        $this->assertStringStartsWith('BV-', $booking->code);
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->payment_status);
        $this->assertSame(3, $booking->nights);
        $this->assertSame(1500000, $booking->total);
        $this->assertSame('full', $booking->payment_option);
        $this->assertNotNull($booking->expires_at);

        $this->assertSame(3, CalendarDay::withoutTenancy()->where('booking_id', $booking->id)->count());
        $this->assertFalse(Availability::isAvailable($this->room, $this->in, $this->out));

        $payment = $booking->payments()->first();
        $this->assertSame('full', $payment->type);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(1500000, $payment->amount);
    }

    public function test_mode_dp_membuat_tagihan_dp_dan_jatuh_tempo_pelunasan(): void
    {
        $this->settings(['payment_mode' => 'dp', 'dp_percent' => 30, 'balance_due_days_before_checkin' => 7]);

        $booking = $this->book();

        $this->assertSame('dp', $booking->payment_option);
        $this->assertSame(450000, $booking->dp_amount);
        $this->assertSame(450000, $booking->payments()->first()->amount);
        $this->assertSame('dp', $booking->payments()->first()->type);
        $this->assertSame(CarbonImmutable::parse($this->in)->subDays(7)->toDateString(), $booking->balance_due_date->toDateString());
    }

    public function test_mode_both_mengikuti_pilihan_tamu_dan_menolak_pilihan_tidak_valid(): void
    {
        $this->settings(['payment_mode' => 'both', 'dp_percent' => 50]);

        $full = $this->book('full');
        $this->assertSame('full', $full->payment_option);
        $this->assertSame(1500000, $full->payments()->first()->amount);

        $dp = $this->book('dp', 'lain@example.com', CarbonImmutable::today()->addDays(40)->toDateString(), CarbonImmutable::today()->addDays(42)->toDateString());
        $this->assertSame(500000, $dp->payments()->first()->amount);

        $this->expectException(BookingRuleViolated::class);

        $this->book(null, 'x@example.com', CarbonImmutable::today()->addDays(50)->toDateString(), CarbonImmutable::today()->addDays(52)->toDateString());
    }

    public function test_tidak_bisa_double_booking_dan_tidak_ada_data_ganda(): void
    {
        $this->book();

        try {
            $this->book(null, 'lain@example.com', CarbonImmutable::parse($this->in)->addDay()->toDateString(), CarbonImmutable::parse($this->out)->addDays(2)->toDateString());
            $this->fail('Seharusnya DatesUnavailable.');
        } catch (DatesUnavailable) {
            // diharapkan
        }

        $this->assertSame(1, Booking::withoutTenancy()->count());
        $this->assertSame(3, CalendarDay::withoutTenancy()->count());
    }

    public function test_aturan_minimal_malam_kapasitas_dan_tanggal_lampau(): void
    {
        $this->room->update(['min_nights' => 4]);

        try {
            $this->book();
            $this->fail('min_nights');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('min_nights', $e->reason);
        }

        $this->room->update(['min_nights' => 1]);

        try {
            $this->book(guests: 9);
            $this->fail('max_guests');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('max_guests', $e->reason);
        }

        try {
            $this->book(in: CarbonImmutable::yesterday()->toDateString(), out: CarbonImmutable::today()->addDay()->toDateString());
            $this->fail('past_date');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('past_date', $e->reason);
        }

        $this->assertSame(0, Booking::withoutTenancy()->count());
    }

    public function test_konfirmasi_dp_lalu_pelunasan(): void
    {
        $this->settings(['payment_mode' => 'dp', 'dp_percent' => 30]);
        $booking = $this->book();

        $booking = BookingService::confirmPayment($booking->pendingPayment());

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Partial, $booking->payment_status);
        $this->assertSame(450000, $booking->paid_amount);
        $this->assertNull($booking->expires_at);
        $this->assertSame(1050000, $booking->remaining());

        try {
            BookingService::recordBalance($booking, 2000000);
            $this->fail('overpay');
        } catch (BookingRuleViolated $e) {
            $this->assertSame('overpay', $e->reason);
        }

        $booking = BookingService::recordBalance($booking, 1050000, 'Tunai saat check-in');

        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertSame(0, $booking->remaining());
        $this->assertSame(2, Payment::withoutTenancy()->where('booking_id', $booking->id)->where('status', 'paid')->count());
    }

    public function test_konfirmasi_pembayaran_penuh_langsung_lunas(): void
    {
        $booking = BookingService::confirmPayment($this->book()->pendingPayment());

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
    }

    public function test_booking_kedaluwarsa_membebaskan_tanggal(): void
    {
        $stale = $this->book();
        $stale->update(['expires_at' => now()->subMinute()]);

        $fresh = $this->book(null, 'lain@example.com', CarbonImmutable::today()->addDays(60)->toDateString(), CarbonImmutable::today()->addDays(62)->toDateString());

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->assertSame(BookingStatus::Expired, $stale->fresh()->status);
        $this->assertSame('expired', $stale->payments()->first()->status);
        $this->assertTrue(Availability::isAvailable($this->room, $this->in, $this->out));
        $this->assertSame(BookingStatus::PendingPayment, $fresh->fresh()->status);
    }

    public function test_pembatalan_membebaskan_tanggal_dan_menutup_pembayaran(): void
    {
        $booking = BookingService::confirmPayment($this->book()->pendingPayment());

        $booking = BookingService::cancel($booking, 'Tamu berhalangan');

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('Tamu berhalangan', $booking->cancel_reason);
        $this->assertTrue(Availability::isAvailable($this->room, $this->in, $this->out));

        $this->expectException(BookingRuleViolated::class);

        BookingService::recordBalance($booking, 1);
    }

    public function test_tamu_dengan_email_sama_dipakai_ulang(): void
    {
        $this->book(null, 'sama@example.com');
        $this->book(null, 'SAMA@example.com', CarbonImmutable::today()->addDays(70)->toDateString(), CarbonImmutable::today()->addDays(72)->toDateString());

        $this->assertSame(1, Guest::withoutTenancy()->count());
        $this->assertSame(2, Booking::withoutTenancy()->count());
    }
}
