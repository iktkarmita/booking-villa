<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/** Model contoh khusus tes untuk memverifikasi trait BelongsToTenant. */
class TestNote extends Model
{
    use BelongsToTenant;

    protected $table = 'notes';

    protected $guarded = [];
}

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->timestamps();
        });

        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();

        // Data dibuat tanpa konteks tenant aktif.
        TestNote::create(['tenant_id' => $this->a->id, 'title' => 'a1']);
        TestNote::create(['tenant_id' => $this->a->id, 'title' => 'a2']);
        TestNote::create(['tenant_id' => $this->b->id, 'title' => 'b1']);
    }

    private function actAs(Tenant $tenant): void
    {
        app(CurrentTenant::class)->set($tenant);
    }

    public function test_tanpa_tenant_aktif_semua_data_terlihat(): void
    {
        $this->assertCount(3, TestNote::all());
    }

    public function test_query_dibatasi_ke_tenant_aktif(): void
    {
        $this->actAs($this->a);

        $this->assertEqualsCanonicalizing(['a1', 'a2'], TestNote::pluck('title')->all());

        $this->actAs($this->b);

        $this->assertSame(['b1'], TestNote::pluck('title')->all());
    }

    public function test_tidak_bisa_memuat_data_tenant_lain_lewat_id(): void
    {
        $noteB = TestNote::withoutTenancy()->where('title', 'b1')->firstOrFail();

        $this->actAs($this->a);

        $this->assertNull(TestNote::find($noteB->id));
    }

    public function test_tenant_id_terisi_otomatis_saat_membuat_data(): void
    {
        $this->actAs($this->a);

        $note = TestNote::create(['title' => 'baru']);

        $this->assertSame($this->a->id, $note->tenant_id);
    }

    public function test_menulis_data_tenant_lain_ditolak(): void
    {
        $this->actAs($this->a);

        $this->expectException(LogicException::class);

        TestNote::create(['tenant_id' => $this->b->id, 'title' => 'curang']);
    }

    public function test_without_tenancy_melihat_semua_data(): void
    {
        $this->actAs($this->a);

        $this->assertCount(3, TestNote::withoutTenancy()->get());
    }

    public function test_run_as_mengembalikan_konteks_semula(): void
    {
        $this->actAs($this->a);

        $titles = app(CurrentTenant::class)->runAs($this->b, fn () => TestNote::pluck('title')->all());

        $this->assertSame(['b1'], $titles);
        $this->assertSame($this->a->id, app(CurrentTenant::class)->id());
    }
}
