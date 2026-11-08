<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.base_domain' => 'localhost']);
    }

    public function test_bahasa_default_mengikuti_pengaturan_tenant(): void
    {
        Tenant::factory()->create(['slug' => 'en-villa', 'subdomain' => 'en-villa', 'default_locale' => 'en']);
        Tenant::factory()->create(['slug' => 'id-villa', 'subdomain' => 'id-villa', 'default_locale' => 'id']);

        $this->get('http://en-villa.localhost/')->assertSee('Welcome');
        $this->get('http://id-villa.localhost/')->assertSee('Selamat datang');
    }

    public function test_pilihan_bahasa_di_sesi_mengalahkan_default_tenant(): void
    {
        Tenant::factory()->create(['slug' => 'en-villa', 'subdomain' => 'en-villa', 'default_locale' => 'en']);

        $this->withSession(['locale' => 'id'])
            ->get('http://en-villa.localhost/')
            ->assertSee('Selamat datang');
    }

    public function test_rute_ganti_bahasa_menyimpan_pilihan_di_sesi(): void
    {
        Tenant::factory()->create(['slug' => 'v', 'subdomain' => 'v']);

        $this->get('http://v.localhost/lang/en')
            ->assertRedirect()
            ->assertSessionHas('locale', 'en');
    }

    public function test_bahasa_tidak_didukung_ditolak(): void
    {
        Tenant::factory()->create(['slug' => 'v', 'subdomain' => 'v']);

        $this->get('http://v.localhost/lang/fr')->assertNotFound();
    }
}
