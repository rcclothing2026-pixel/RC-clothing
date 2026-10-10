<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user — logs in via SMS OTP with this phone number.
        User::factory()->create([
            'name' => 'مدیر چیاکو',
            'phone' => '09120000000',
            'phone_verified_at' => now(),
            'email' => 'admin@chiiaco.test',
            'is_admin' => true,
        ]);

        $this->call([
            CatalogSeeder::class,
            ShippingSeeder::class,
            PaymentMethodSeeder::class,
            PageSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
