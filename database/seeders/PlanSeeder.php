<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Paket berdasarkan total kamar aktif di semua villa milik tenant. Aman dijalankan berulang. */
    public function run(): void
    {
        $plans = [
            ['slug' => 'starter', 'name' => 'Starter', 'price' => 150000, 'max_rooms' => 10],
            ['slug' => 'growth', 'name' => 'Growth', 'price' => 250000, 'max_rooms' => 20],
            ['slug' => 'business', 'name' => 'Business', 'price' => 350000, 'max_rooms' => 30],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['interval' => 'month', 'is_active' => true, 'features' => []]
            );
        }
    }
}
