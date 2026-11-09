<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BookingService;
use App\Support\CurrentTenant;
use App\Support\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        app()->setLocale('id');

        $this->tenant = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);
        $this->room = Room::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 500000, 'max_guests' => 4]);
        app(CurrentTenant::class)->set($this->tenant);
    }

    private function paidBooking(Room $room, int $startOffset, string $name = 'Budi'): Booking
    {
        $booking = BookingService::create(
            $room->fresh(),
            CarbonImmutable::today()->addDays($startOffset)->toDateString(),
            CarbonImmutable::today()->addDays($startOffset + 3)->toDateString(),
            2,
            ['name' => $name, 'email' => 'tamu@example.com', 'phone' => '081234567890'],
            null,
            'id',
        );

        return BookingService::confirmPayment(Payment::withoutTenancy()->where('booking_id', $booking->id)->firstOrFail());
    }

    public function test_ringkasan_menghitung_pendapatan_okupansi_dan_per_villa(): void
    {
        $this->paidBooking($this->room, 2); // 3 malam, Rp 1.500.000

        $from = CarbonImmutable::today();
        $to = CarbonImmutable::today()->addDays(9); // 10 hari, 1 kamar aktif => kapasitas 10 malam

        $summary = ReportService::summary($from, $to);

        $this->assertSame(1500000, $summary['revenue']);
        $this->assertSame(0, $summary['refunded']);
        $this->assertSame(1500000, $summary['net']);
        $this->assertSame(10, $summary['days']);
        $this->assertSame(3, $summary['nights_sold']);
        $this->assertSame(10, $summary['nights_capacity']);
        $this->assertSame(30.0, $summary['occupancy']);
        $this->assertSame(1, $summary['bookings']['confirmed']);
        $this->assertCount(1, $summary['villas']);
        $this->assertSame(1500000, $summary['villas'][0]['revenue']);
        $this->assertSame(3, $summary['villas'][0]['nights']);
    }

    public function test_rentang_terbalik_ditukar_dan_periode_kosong_tidak_error(): void
    {
        $summary = ReportService::summary(CarbonImmutable::today()->addDays(5), CarbonImmutable::today());

        $this->assertSame(6, $summary['days']);
        $this->assertSame(0, $summary['revenue']);
        $this->assertSame(0.0, $summary['occupancy']);
    }

    public function test_laporan_tidak_mencampur_data_tenant_lain(): void
    {
        $this->paidBooking($this->room, 2);

        $current = app(CurrentTenant::class);
        $current->forget();

        $other = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $other->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);

        $current->runAs($other, function () use ($other) {
            $otherRoom = Room::factory()->create(['tenant_id' => $other->id, 'base_price' => 900000, 'max_guests' => 4]);
            $this->paidBooking($otherRoom, 2, 'Orang Lain');
        });

        $current->set($this->tenant);

        $summary = ReportService::summary(CarbonImmutable::today(), CarbonImmutable::today()->addDays(9));

        $this->assertSame(1500000, $summary['revenue']);
        $this->assertSame(3, $summary['nights_sold']);

        $csv = ReportService::csv(CarbonImmutable::today(), CarbonImmutable::today()->addDays(9));
        $this->assertStringContainsString('Budi', $csv);
        $this->assertStringNotContainsString('Orang Lain', $csv);
    }

    public function test_csv_menetralkan_formula_injection(): void
    {
        $this->paidBooking($this->room, 2, '=HYPERLINK("http://jahat.example")');

        $csv = ReportService::csv(CarbonImmutable::today(), CarbonImmutable::today()->addDays(9));

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBFcode,villa", $csv);
    }

    public function test_halaman_laporan_tampil_untuk_owner_dan_ditolak_untuk_tenant_lain(): void
    {
        $current = app(CurrentTenant::class);
        $current->forget();
        $other = Tenant::factory()->create();
        $current->set($this->tenant);

        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner, ['role' => 'owner']);
        $this->paidBooking($this->room, 2);

        $this->actingAs($owner)
            ->get('/admin/'.$this->tenant->slug.'/reports')
            ->assertOk()
            ->assertSee('Rp 1.500.000');

        $status = $this->actingAs($owner)->get('/admin/'.$other->slug.'/reports')->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }
}
