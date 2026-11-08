<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            'wifi' => ['Wi-Fi', 'Wi-Fi'],
            'ac' => ['AC', 'Air conditioning'],
            'pool' => ['Kolam renang', 'Swimming pool'],
            'kitchen' => ['Dapur', 'Kitchen'],
            'parking' => ['Parkir', 'Parking'],
            'breakfast' => ['Sarapan', 'Breakfast'],
            'tv' => ['TV', 'TV'],
            'hot-water' => ['Air panas', 'Hot water'],
            'bbq' => ['Area BBQ', 'BBQ area'],
            'garden' => ['Taman', 'Garden'],
            'airport-transfer' => ['Antar-jemput bandara', 'Airport transfer'],
            'washing-machine' => ['Mesin cuci', 'Washing machine'],
        ];

        foreach ($amenities as $code => [$id, $en]) {
            Amenity::updateOrCreate(['code' => $code], ['name' => ['id' => $id, 'en' => $en]]);
        }
    }
}
