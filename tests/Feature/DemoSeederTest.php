<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Villa;
use Database\Seeders\DemoVillaSeeder;
use Database\Seeders\SetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_demo_membuat_villa_dan_kamar_dan_aman_diulang(): void
    {
        $this->seed(SetupSeeder::class);
        $this->seed(DemoVillaSeeder::class);

        $this->assertSame(2, Villa::withoutTenancy()->count());
        $this->assertSame(3, Room::withoutTenancy()->count());
    }
}
