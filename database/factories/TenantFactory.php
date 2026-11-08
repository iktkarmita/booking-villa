<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $slug = 'villa-'.Str::lower(Str::random(8));

        return [
            'name' => 'Villa '.Str::title(Str::random(5)),
            'slug' => $slug,
            'subdomain' => $slug,
            'status' => TenantStatus::Active->value,
            'default_locale' => 'id',
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => TenantStatus::Suspended->value]);
    }
}
