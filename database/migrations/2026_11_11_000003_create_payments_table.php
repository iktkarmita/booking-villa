<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // full|dp|balance
            $table->string('gateway')->default('manual');
            $table->string('gateway_ref')->nullable()->unique();
            $table->string('method', 10)->default('manual'); // manual|qris
            $table->decimal('amount', 12, 0);
            $table->string('status', 10)->default('pending'); // pending|paid|expired|failed|refunded
            $table->text('qr_string')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('proof_path')->nullable(); // bukti transfer dari tamu (pembayaran manual)
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
