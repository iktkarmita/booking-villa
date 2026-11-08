<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu malam yang terkunci untuk satu kamar.
        // UNIQUE(room_id, date) adalah pagar terakhir anti double-booking.
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10); // booking|block
            $table->unsignedBigInteger('booking_id')->nullable(); // FK ditambahkan di fase 3 saat tabel bookings ada
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
    }
};
