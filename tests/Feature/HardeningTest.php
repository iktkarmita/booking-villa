<?php

namespace Tests\Feature;

use App\Livewire\Site\Checkout;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['booking.base_domain' => 'localhost', 'app.url' => 'http://localhost:8000']);
        app()->setLocale('id');

        $this->tenant = Tenant::factory()->create(['slug' => 'bunga', 'subdomain' => 'bunga']);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);
        $this->room = Room::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 500000, 'max_guests' => 4]);
        app(\App\Support\CurrentTenant::class)->set($this->tenant);
    }

    public function test_respons_membawa_header_keamanan(): void
    {
        $response = $this->get('http://bunga.localhost/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_checkout_dibatasi_lajunya_per_ip(): void
    {
        RateLimiter::clear('site:checkout:127.0.0.1');

        $component = Livewire::test(Checkout::class, [
            'roomId' => $this->room->id,
            'checkIn' => CarbonImmutable::today()->addDays(10)->toDateString(),
            'checkOut' => CarbonImmutable::today()->addDays(12)->toDateString(),
            'guests' => 2,
        ]);

        // Data kosong: validasi gagal, tetapi setiap percobaan tetap dihitung.
        for ($i = 0; $i < 6; $i++) {
            $component->call('submit')->assertHasErrors(['name']);
        }

        $component->call('submit')->assertHasErrors(['checkIn']);
        $this->assertStringContainsString('Terlalu banyak percobaan', $component->errors()->first('checkIn'));
    }
}
