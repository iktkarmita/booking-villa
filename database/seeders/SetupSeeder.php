<?php

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeder awal: paket + akun super admin (+ tenant demo di lokal).
 * Jalankan: php artisan db:seed --class=SetupSeeder
 */
class SetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);
        $this->call(AmenitySeeder::class);

        $password = config('booking.admin_password');

        if (blank($password)) {
            if (! app()->environment('local')) {
                throw new RuntimeException('Isi BOOKING_ADMIN_PASSWORD di .env sebelum seed di luar environment local.');
            }

            $password = 'password';
        }

        $admin = User::query()->firstOrNew(['email' => config('booking.admin_email')]);

        if (! $admin->exists) {
            $admin->name = 'Super Admin';
            $admin->password = $password;
        }

        $admin->is_super_admin = true;
        $admin->save();

        if (app()->environment('local')) {
            $this->seedDemoTenant();
            $this->call(DemoVillaSeeder::class);
        }
    }

    private function seedDemoTenant(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Villa Demo', 'subdomain' => 'demo', 'status' => TenantStatus::Active->value]
        );

        $owner = User::query()->firstOrNew(['email' => 'owner@example.com']);

        if (! $owner->exists) {
            $owner->name = 'Owner Demo';
            $owner->password = 'password';
            $owner->save();
        }

        $tenant->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

        if (! $tenant->subscriptions()->exists()) {
            $tenant->subscriptions()->create([
                'plan_id' => Plan::where('slug', 'starter')->value('id'),
                'status' => SubscriptionStatus::Trial->value,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays(14)->toDateString(),
            ]);
        }
    }
}
