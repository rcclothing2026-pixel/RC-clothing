<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production base config — the essential rows a fresh live store needs so
 * checkout, nav, and legal pages work. Intentionally does NOT seed demo
 * products (CatalogSeeder) or test data (SmokeSeeder): the real catalog is
 * imported from StoqS / added in admin.
 *
 * Safe to run once on first deploy:  bash scripts/deploy.sh --seed
 * (The individual seeders guard against duplicating existing rows.)
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ShippingSeeder::class,        // shipping methods (checkout needs these)
            PaymentMethodSeeder::class,   // payment gateways
            PageSeeder::class,            // about / faq / terms / privacy
            MenuSeeder::class,            // header + footer navigation
        ]);
    }
}
