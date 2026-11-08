<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Tenant;
use App\Models\Villa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $name = 'Kamar '.Str::title(Str::random(4));

        return [
            'tenant_id' => Tenant::factory(),
            // Villa selalu milik tenant yang sama dengan kamar.
            'villa_id' => fn (array $attributes) => Villa::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => ['id' => $name, 'en' => $name],
            'bedrooms' => 1,
            'bathrooms' => 1,
            'max_guests' => 2,
            'base_price' => 500000,
            'min_nights' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
