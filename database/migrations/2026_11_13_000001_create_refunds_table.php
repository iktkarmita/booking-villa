<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('requested_by', 10)->default('guest'); // guest|owner
            $table->text('reason')->nullable();
            $table->string('status', 15)->default('requested'); // requested|approved|rejected|refunded
            $table->unsignedBigInteger('suggested_amount')->default(0); // hitungan kebijakan saat diajukan
            $table->unsignedBigInteger('amount_approved')->nullable();
            $table->string('dest_bank', 100)->nullable();
            $table->string('dest_account_name', 100)->nullable();
            $table->text('dest_account_number')->nullable(); // terenkripsi lewat cast model
            $table->string('proof_path')->nullable();
            $table->text('owner_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
