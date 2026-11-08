<?php

namespace Tests\Feature;

use App\Exceptions\DatesUnavailable;
use App\Models\CalendarDay;
use App\Models\Plan;
use App\Models\Room;
use App\Models\SeasonalPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Availability;
use App\Support\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AvailabilityPricingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);
        $this->room = Room::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 500000, 'min_nights' => 1]);
    }

    private function season(string $start, string $end, int $price, ?int $minNights = null, string $label = 'Musim'): SeasonalPrice
    {
        return SeasonalPrice::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $this->room->id,
            'label' => $label,
            'start_date' => $start,
            'end_date' => $end,
            'price_per_night' => $price,
            'min_nights' => $minNights,
        ]);
    }

    public function test_harga_default_memakai_harga_dasar_per_malam(): void
    {
        $quote = PriceCalculator::quote($this->room, '2027-03-10', '2027-03-13');

        $this->assertSame(3, $quote['nights']);
        $this->assertSame(1500000, $quote['total']);
        $this->assertCount(3, $quote['nightly']);
    }

    public function test_harga_musiman_menimpa_hanya_malam_di_dalam_rentang(): void
    {
        $this->season('2027-12-24', '2027-12-26', 900000);

        // Malam: 23 (dasar), 24 (musim), 25 (musim), 26 (musim), 27 (dasar). Check-out 28 tidak dihitung.
        $quote = PriceCalculator::quote($this->room, '2027-12-23', '2027-12-28');

        $this->assertSame(500000 + 900000 * 3 + 500000, $quote['total']);
        $this->assertSame(900000, $quote['nightly']['2027-12-26']);
        $this->assertSame(500000, $quote['nightly']['2027-12-27']);
    }

    public function test_musim_tumpang_tindih_yang_terbaru_menang(): void
    {
        $this->season('2027-08-01', '2027-08-31', 700000, label: 'Tinggi');
        $this->season('2027-08-15', '2027-08-17', 1200000, label: 'Hari kemerdekaan');

        $quote = PriceCalculator::quote($this->room, '2027-08-14', '2027-08-18');

        $this->assertSame(700000, $quote['nightly']['2027-08-14']);
        $this->assertSame(1200000, $quote['nightly']['2027-08-15']);
        $this->assertSame(1200000, $quote['nightly']['2027-08-17']);
    }

    public function test_minimal_malam_mengikuti_musim_yang_lebih_ketat(): void
    {
        $this->season('2027-12-24', '2027-12-31', 900000, minNights: 3);

        $short = PriceCalculator::quote($this->room, '2027-12-25', '2027-12-27');
        $long = PriceCalculator::quote($this->room, '2027-12-25', '2027-12-28');

        $this->assertSame(3, $short['min_nights']);
        $this->assertFalse($short['meets_min_nights']);
        $this->assertTrue($long['meets_min_nights']);
    }

    public function test_rentang_tanggal_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PriceCalculator::quote($this->room, '2027-03-10', '2027-03-10');
    }

    public function test_hari_checkout_tetap_bebas(): void
    {
        Availability::block($this->room, '2027-05-10', '2027-05-12');

        $this->assertTrue(Availability::isAvailable($this->room, '2027-05-08', '2027-05-10'));
        $this->assertFalse(Availability::isAvailable($this->room, '2027-05-09', '2027-05-11'));
        $this->assertFalse(Availability::isAvailable($this->room, '2027-05-12', '2027-05-14'));
        $this->assertTrue(Availability::isAvailable($this->room, '2027-05-13', '2027-05-15'));
    }

    public function test_kamar_nonaktif_tidak_tersedia(): void
    {
        $this->room->update(['is_active' => false]);

        $this->assertFalse(Availability::isAvailable($this->room->fresh(), '2027-06-01', '2027-06-03'));
    }

    public function test_lock_mencegah_double_booking_dan_tidak_menyisakan_data_parsial(): void
    {
        Availability::lock($this->room, '2027-07-01', '2027-07-04', bookingId: 1);

        try {
            Availability::lock($this->room, '2027-07-03', '2027-07-06', bookingId: 2);
            $this->fail('Seharusnya DatesUnavailable.');
        } catch (DatesUnavailable) {
            // diharapkan
        }

        $this->assertSame(3, CalendarDay::withoutTenancy()->where('room_id', $this->room->id)->count());
        $this->assertSame(0, CalendarDay::withoutTenancy()->where('booking_id', 2)->count());
    }

    public function test_release_membebaskan_tanggal_booking(): void
    {
        Availability::lock($this->room, '2027-07-01', '2027-07-04', bookingId: 7);

        $this->assertSame(3, Availability::release(7));
        $this->assertTrue(Availability::isAvailable($this->room, '2027-07-01', '2027-07-04'));
    }

    public function test_blokir_melewati_hari_yang_sudah_diblokir_dan_menolak_bila_ada_booking(): void
    {
        $this->assertSame(3, Availability::block($this->room, '2027-09-01', '2027-09-03'));
        $this->assertSame(2, Availability::block($this->room, '2027-09-02', '2027-09-05'));

        Availability::lock($this->room, '2027-10-10', '2027-10-12', bookingId: 3);

        $this->expectException(DatesUnavailable::class);

        Availability::block($this->room, '2027-10-09', '2027-10-11');
    }

    public function test_buka_blokir_tidak_menghapus_booking(): void
    {
        Availability::block($this->room, '2027-11-01', '2027-11-02');
        Availability::lock($this->room, '2027-11-03', '2027-11-05', bookingId: 4);

        $this->assertSame(2, Availability::unblock($this->room, '2027-11-01', '2027-11-05'));
        $this->assertSame(2, CalendarDay::withoutTenancy()->where('room_id', $this->room->id)->count());
    }

    public function test_owner_bisa_membuka_halaman_kalender_dan_harga_musiman(): void
    {
        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner, ['role' => 'owner']);
        $this->season('2027-12-24', '2027-12-31', 900000);
        Availability::block($this->room, now()->toDateString(), now()->addDays(2)->toDateString(), 'Perawatan');

        $this->actingAs($owner);

        $this->get('/admin/'.$this->tenant->slug.'/calendar')->assertOk();
        $this->get('/admin/'.$this->tenant->slug.'/seasonal-prices')->assertOk();
    }
}
