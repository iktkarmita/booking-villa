<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => 'Paket '.Str::title(Str::random(4)),
            'slug' => 'paket-'.Str::lower(Str::random(8)),
            'price' => 150000,
            'interval' => 'month',
            'max_rooms' => 10,
            'features' => [],
            'is_active' => true,
        ];
    }
}
