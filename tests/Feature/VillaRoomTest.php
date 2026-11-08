<?php

namespace Tests\Feature;

use App\Exceptions\RoomQuotaExceeded;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Villa;
use App\Support\CurrentTenant;
use App\Support\RoomQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VillaRoomTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithPlan(int $maxRooms): Tenant
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::factory()->create(['max_rooms' => $maxRooms])->id,
        ]);

        return $tenant;
    }

    public function test_slug_villa_dibuat_otomatis_dan_unik_per_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $v1 = Villa::factory()->create(['tenant_id' => $a->id, 'name' => ['id' => 'Villa Sunset', 'en' => '']]);
        $v2 = Villa::factory()->create(['tenant_id' => $a->id, 'name' => ['id' => 'Villa Sunset', 'en' => '']]);
        $v3 = Villa::factory()->create(['tenant_id' => $b->id, 'name' => ['id' => 'Villa Sunset', 'en' => '']]);

        $this->assertSame('villa-sunset', $v1->slug);
        $this->assertSame('villa-sunset-2', $v2->slug);
        $this->assertSame('villa-sunset', $v3->slug);
    }

    public function test_teks_dua_bahasa_dengan_fallback(): void
    {
        $villa = Villa::factory()->create(['name' => ['id' => 'Villa Pantai', 'en' => 'Beach Villa']]);

        $this->assertSame('Villa Pantai', $villa->localized('name', 'id'));
        $this->assertSame('Beach Villa', $villa->localized('name', 'en'));

        $villa->name = ['id' => 'Villa Pantai', 'en' => ''];

        $this->assertSame('Villa Pantai', $villa->localized('name', 'en'));
    }

    public function test_kamar_aktif_tidak_boleh_melebihi_kuota_paket(): void
    {
        $tenant = $this->tenantWithPlan(2);

        Room::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertSame(0, RoomQuota::remaining($tenant));

        $this->expectException(RoomQuotaExceeded::class);

        Room::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_kamar_nonaktif_tidak_dihitung_dan_aktivasi_dicek(): void
    {
        $tenant = $this->tenantWithPlan(1);

        Room::factory()->create(['tenant_id' => $tenant->id]);
        $extra = Room::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $this->assertSame(1, RoomQuota::used($tenant));

        $this->expectException(RoomQuotaExceeded::class);

        $extra->update(['is_active' => true]);
    }

    public function test_menonaktifkan_kamar_membebaskan_kuota(): void
    {
        $tenant = $this->tenantWithPlan(1);

        $first = Room::factory()->create(['tenant_id' => $tenant->id]);
        $second = Room::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $first->update(['is_active' => false]);
        $second->update(['is_active' => true]);

        $this->assertSame(1, RoomQuota::used($tenant));
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_kamar_aktif_boleh_diedit_saat_kuota_penuh(): void
    {
        $tenant = $this->tenantWithPlan(1);
        $room = Room::factory()->create(['tenant_id' => $tenant->id]);

        $room->update(['base_price' => 900000]);

        $this->assertSame(900000, $room->fresh()->base_price);
    }

    public function test_kamar_terhapus_tidak_dihitung_ke_kuota(): void
    {
        $tenant = $this->tenantWithPlan(1);
        $room = Room::factory()->create(['tenant_id' => $tenant->id]);

        $room->delete();

        $this->assertSame(0, RoomQuota::used($tenant));
        $this->assertTrue(RoomQuota::canActivate($tenant));
    }

    public function test_tanpa_langganan_tidak_bisa_mengaktifkan_kamar(): void
    {
        $tenant = Tenant::factory()->create();

        $this->expectException(RoomQuotaExceeded::class);

        Room::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_villa_dan_kamar_terisolasi_antar_tenant(): void
    {
        $a = $this->tenantWithPlan(5);
        $b = $this->tenantWithPlan(5);

        $roomA = Room::factory()->create(['tenant_id' => $a->id]);
        Room::factory()->create(['tenant_id' => $b->id]);

        app(CurrentTenant::class)->set($a);

        $this->assertSame([$roomA->id], Room::pluck('id')->all());
        $this->assertSame(1, Villa::count());
    }

    public function test_owner_bisa_membuka_halaman_villa_dan_kamar(): void
    {
        $tenant = $this->tenantWithPlan(5);
        $owner = User::factory()->create();
        $tenant->users()->attach($owner, ['role' => 'owner']);
        Room::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner);

        $this->get('/admin/'.$tenant->slug.'/villas')->assertOk();
        $this->get('/admin/'.$tenant->slug.'/villas/create')->assertOk();
        $this->get('/admin/'.$tenant->slug.'/rooms')->assertOk();
        $this->get('/admin/'.$tenant->slug)->assertOk();
    }
}
