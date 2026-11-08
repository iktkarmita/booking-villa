<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master fasilitas: dipakai bersama semua tenant (dikelola platform).
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->json('name'); // {"id": "...", "en": "..."}
            $table->timestamps();
        });

        Schema::create('amenity_villa', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->primary(['amenity_id', 'villa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_villa');
        Schema::dropIfExists('amenities');
    }
};
