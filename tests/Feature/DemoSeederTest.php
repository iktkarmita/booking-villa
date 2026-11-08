<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Villa;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\DemoVillaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_demo_membuat_villa_dan_kamar_dan_aman_diulang(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'subdomain' => 'demo']);
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => 10])->id,
        ]);

        $this->seed(AmenitySeeder::class);
        $this->seed(DemoVillaSeeder::class);
        $this->seed(DemoVillaSeeder::class);

        $this->assertSame(2, Villa::withoutTenancy()->count());
        $this->assertSame(3, Room::withoutTenancy()->count());
        $this->assertSame('both', $tenant->settings()->value('payment_mode'));
    }

    public function test_seeder_demo_tidak_error_bila_tenant_demo_belum_ada(): void
    {
        $this->seed(DemoVillaSeeder::class);

        $this->assertSame(0, Villa::withoutTenancy()->count());
    }
}
