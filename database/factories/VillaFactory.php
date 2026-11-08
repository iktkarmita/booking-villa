<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Villa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Villa> */
class VillaFactory extends Factory
{
    protected $model = Villa::class;

    public function definition(): array
    {
        $name = 'Villa '.Str::title(Str::random(6));

        return [
            'tenant_id' => Tenant::factory(),
            'name' => ['id' => $name, 'en' => $name],
            'description' => ['id' => 'Villa nyaman dekat pantai.', 'en' => 'Cozy villa near the beach.'],
            'address' => 'Jl. Pantai No. 1, Bali',
            'is_active' => true,
        ];
    }
}
