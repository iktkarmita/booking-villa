<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('name'); // {"id": "...", "en": "..."}
            $table->string('slug');
            $table->json('description')->nullable();
            $table->string('address')->nullable();
            $table->string('check_in_time', 5)->default('14:00');
            $table->string('check_out_time', 5)->default('12:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villas');
    }
};
