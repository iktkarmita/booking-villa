<?php

namespace Tests\Feature;

use App\Livewire\Site\BookingStatus;
use App\Livewire\Site\Checkout;
use App\Livewire\Site\VillaShow;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Villa;
use App\Support\Availability;
use App\Support\BookingService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Villa $villa;

    private Room $room;

    private string $in;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.base_domain' => 'localhost']);

        $this->tenant = Tenant::factory()->create(['slug' => 'bunga', 'subdomain' => 'bunga', 'name' => 'Villa Bunga']);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);

        $this->villa = Villa::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['id' => 'Villa Mawar', 'en' => 'Rose Villa']]);
        $this->room = Room::factory()->create([
            'tenant_id' => $this->tenant->id,
            'villa_id' => $this->villa->id,
            'name' => ['id' => 'Kamar Deluxe', 'en' => 'Deluxe Room'],
            'base_price' => 500000,
            'max_guests' => 4,
        ]);

        $this->in = CarbonImmutable::today()->addDays(30)->toDateString();
        $this->out = CarbonImmutable::today()->addDays(33)->toDateString();

        app()->setLocale('id');
    }

    private function asTenant(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
    }

    public function test_beranda_tenant_menampilkan_villa_miliknya_saja(): void
    {
        $other = Tenant::factory()->create(['slug' => 'lain', 'subdomain' => 'lain']);
        Subscription::factory()->create(['tenant_id' => $other->id, 'plan_id' => Plan::factory()->create()->id]);
        Room::factory()->create([
            'tenant_id' => $other->id,
            'villa_id' => Villa::factory()->create(['tenant_id' => $other->id, 'name' => ['id' => 'Villa Rahasia', 'en' => 'Secret']])->id,
        ]);

        $this->get('http://bunga.localhost/')
            ->assertOk()
            ->assertSee('Villa Mawar')
            ->assertDontSee('Villa Rahasia');
    }

    public function test_villa_tanpa_kamar_aktif_tidak_tampil_di_beranda(): void
    {
        Villa::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['id' => 'Villa Kosong', 'en' => 'Empty']]);

        $this->get('http://bunga.localhost/')->assertOk()->assertDontSee('Villa Kosong');
    }

    public function test_halaman_villa_menampilkan_kamar_dan_fasilitas(): void
    {
        $amenity = Amenity::create(['code' => 'pool', 'name' => ['id' => 'Kolam renang', 'en' => 'Pool']]);
        $this->villa->amenities()->attach($amenity);

        $this->get('http://bunga.localhost/villas/'.$this->villa->slug)
            ->assertOk()
            ->assertSee('Villa Mawar')
            ->assertSee('Kamar Deluxe')
            ->assertSee('Kolam renang');
    }

    public function test_villa_tidak_dikenal_atau_nonaktif_404(): void
    {
        $this->get('http://bunga.localhost/villas/tidak-ada')->assertNotFound();

        $this->villa->update(['is_active' => false]);

        $this->get('http://bunga.localhost/villas/'.$this->villa->slug)->assertNotFound();
    }

    public function test_halaman_tenant_tidak_tersedia_di_host_pusat(): void
    {
        $this->get('http://localhost/villas/'.$this->villa->slug)->assertNotFound();
        $this->get('http://localhost/booking/BV-XXXX')->assertNotFound();
    }

    public function test_cek_ketersediaan_menampilkan_harga_total(): void
    {
        $this->asTenant();

        Livewire::test(VillaShow::class, ['slug' => $this->villa->slug])
            ->set('checkIn', $this->in)
            ->set('checkOut', $this->out)
            ->set('guests', 2)
            ->call('search')
            ->assertHasNoErrors()
            ->assertSee('1.500.000');
    }

    public function test_cek_ketersediaan_menolak_tanggal_tidak_valid(): void
    {
        $this->asTenant();

        Livewire::test(VillaShow::class, ['slug' => $this->villa->slug])
            ->set('checkIn', $this->out)
            ->set('checkOut', $this->in)
            ->call('search')
            ->assertHasErrors(['checkOut']);
    }

    public function test_checkout_membuat_booking_dan_mengarahkan_ke_halaman_status(): void
    {
        $this->asTenant();

        Livewire::test(Checkout::class, ['roomId' => $this->room->id, 'checkIn' => $this->in, 'checkOut' => $this->out, 'guests' => 2])
            ->set('name', 'Budi Santoso')
            ->set('email', 'budi@example.com')
            ->set('phone', '081234567890')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $booking = Booking::query()->firstOrFail();

        $this->assertSame('pending_payment', $booking->status->value);
        $this->assertSame(1500000, $booking->total);
    }

    public function test_checkout_memvalidasi_data_pemesan(): void
    {
        $this->asTenant();

        Livewire::test(Checkout::class, ['roomId' => $this->room->id, 'checkIn' => $this->in, 'checkOut' => $this->out, 'guests' => 2])
            ->set('email', 'bukan-email')
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'phone']);

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_checkout_menolak_tanggal_yang_baru_terisi(): void
    {
        $this->asTenant();

        $component = Livewire::test(Checkout::class, ['roomId' => $this->room->id, 'checkIn' => $this->in, 'checkOut' => $this->out, 'guests' => 2]);

        Availability::block($this->room, $this->in, $this->in, 'diblokir');

        $component
            ->set('name', 'Budi')
            ->set('email', 'budi@example.com')
            ->set('phone', '081234567890')
            ->call('submit')
            ->assertHasErrors(['checkIn']);

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_halaman_status_booking_dibuka_dengan_kode(): void
    {
        $this->tenant->settings()->update(['bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'bank_account_name' => 'Pak Owner']);
        $booking = $this->makeBooking();

        $this->get('http://bunga.localhost/booking/'.$booking->code)
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('1234567890')
            ->assertSee('1.500.000');

        $this->get('http://bunga.localhost/booking/BV-TIDAKADA')->assertNotFound();
    }

    public function test_kode_booking_tenant_lain_tidak_bisa_dibuka(): void
    {
        $booking = $this->makeBooking();
        Tenant::factory()->create(['slug' => 'lain', 'subdomain' => 'lain']);

        $this->get('http://lain.localhost/booking/'.$booking->code)->assertNotFound();
    }

    public function test_tamu_bisa_mengunggah_bukti_transfer(): void
    {
        Storage::fake('public');
        Storage::fake();

        $booking = $this->makeBooking();
        $this->asTenant();

        Livewire::test(BookingStatus::class, ['code' => $booking->code])
            ->set('proof', UploadedFile::fake()->image('bukti.jpg'))
            ->call('uploadProof')
            ->assertHasNoErrors();

        $path = $booking->payments()->first()->fresh()->proof_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_owner_bisa_membuka_halaman_booking_dan_pengaturan(): void
    {
        $this->makeBooking();
        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner);

        $this->get('/admin/bunga/bookings')->assertOk()->assertSee('BV-');
        $this->get('/admin/bunga/tenant-settings')->assertOk();
    }

    private function makeBooking(): Booking
    {
        return BookingService::create(
            $this->room->fresh(),
            $this->in,
            $this->out,
            2,
            ['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '081234567890'],
        );
    }
}
