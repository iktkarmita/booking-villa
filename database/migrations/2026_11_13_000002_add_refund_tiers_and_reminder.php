<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            // Tingkat refund: [{"days": 7, "percent": 100}, ...] (H-minimal berapa hari -> berapa persen).
            $table->json('refund_tiers')->nullable();
        });

        DB::table('tenant_settings')->whereNull('refund_tiers')->update([
            'refund_tiers' => json_encode([['days' => 7, 'percent' => 100], ['days' => 3, 'percent' => 50]]),
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('balance_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', fn (Blueprint $table) => $table->dropColumn('refund_tiers'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('balance_reminded_at'));
    }
};
