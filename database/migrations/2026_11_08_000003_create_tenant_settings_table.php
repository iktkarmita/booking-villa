<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            // Pembayaran tamu: full | dp | both
            $table->string('payment_mode')->default('full');
            $table->unsignedTinyInteger('dp_percent')->default(30);
            $table->unsignedTinyInteger('balance_due_days_before_checkin')->default(7);

            // Gateway milik tenant (kredensial terenkripsi lewat cast model)
            $table->string('gateway')->default('manual'); // manual|ipaymu|duitku
            $table->text('gateway_credentials')->nullable();

            // WhatsApp / notifikasi
            $table->string('wa_number')->nullable();
            $table->string('wa_driver')->default('link'); // link|fonnte|meta
            $table->text('wa_credentials')->nullable();
            $table->string('notify_email')->nullable();

            $table->json('cancellation_policy')->nullable(); // {"id": "...", "en": "..."}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
