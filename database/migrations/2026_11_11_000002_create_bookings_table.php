<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->foreignId('room_id')->constrained();
            $table->foreignId('guest_id')->constrained();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('guests_count');
            $table->string('status')->default('pending_payment');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_option')->default('full'); // full|dp
            $table->decimal('total', 12, 0);
            $table->decimal('dp_amount', 12, 0)->nullable();
            $table->decimal('paid_amount', 12, 0)->default(0);
            $table->date('balance_due_date')->nullable();
            $table->string('locale', 5)->default('id');
            $table->string('source', 20)->default('web');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['room_id', 'check_in', 'check_out']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
