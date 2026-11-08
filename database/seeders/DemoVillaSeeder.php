<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Tenant;
use App\Models\Villa;
use App\Support\CurrentTenant;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk tenant "demo" (hanya lokal). Aman dijalankan berulang.
 * Jalankan: php artisan db:seed --class=DemoVillaSeeder
 */
class DemoVillaSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'demo')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant demo belum ada. Jalankan SetupSeeder dulu.');

            return;
        }

        $tenant->settings()->update([
            'payment_mode' => 'both',
            'dp_percent' => 30,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Owner Demo',
            'wa_number' => '6281234567890',
        ]);

        app(CurrentTenant::class)->runAs($tenant, function () use ($tenant) {
            $villas = [
                [
                    'name' => ['id' => 'Villa Sawah Ubud', 'en' => 'Ubud Rice Field Villa'],
                    'description' => [
                        'id' => 'Villa tenang dengan pemandangan sawah dan kolam renang pribadi.',
                        'en' => 'Quiet villa overlooking rice fields with a private pool.',
                    ],
                    'address' => 'Ubud, Gianyar, Bali',
                    'rooms' => [
                        ['Kamar Deluxe', 'Deluxe Room', 1, 2, 650000],
                        ['Kamar Keluarga', 'Family Room', 2, 4, 1100000],
                    ],
                    'amenities' => ['wifi', 'ac', 'pool', 'breakfast', 'parking'],
                ],
                [
                    'name' => ['id' => 'Villa Pantai Canggu', 'en' => 'Canggu Beach Villa'],
                    'description' => [
                        'id' => 'Dekat pantai dan kafe, cocok untuk liburan keluarga.',
                        'en' => 'Close to the beach and cafes, great for family trips.',
                    ],
                    'address' => 'Canggu, Badung, Bali',
                    'rooms' => [
                        ['Seluruh Villa', 'Entire Villa', 3, 6, 2500000],
                    ],
                    'amenities' => ['wifi', 'ac', 'pool', 'kitchen', 'bbq'],
                ],
            ];

            foreach ($villas as $data) {
                $villa = Villa::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => \Illuminate\Support\Str::slug($data['name']['id'])],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'address' => $data['address'],
                    ]
                );

                $villa->amenities()->sync(
                    \App\Models\Amenity::query()->whereIn('code', $data['amenities'])->pluck('id')
                );

                foreach ($data['rooms'] as [$id, $en, $bedrooms, $guests, $price]) {
                    Room::query()->firstOrCreate(
                        ['villa_id' => $villa->id, 'name->id' => $id],
                        [
                            'tenant_id' => $tenant->id,
                            'name' => ['id' => $id, 'en' => $en],
                            'bedrooms' => $bedrooms,
                            'bathrooms' => $bedrooms,
                            'max_guests' => $guests,
                            'base_price' => $price,
                            'min_nights' => 1,
                            'is_active' => true,
                        ]
                    );
                }
            }
        });
    }
}
