<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'پست پیشتاز', 'description' => 'تحویل ۳ تا ۵ روز کاری', 'price' => 45_000, 'free_over' => 1_000_000, 'position' => 0],
            ['name' => 'تیپاکس', 'description' => 'تحویل سریع درب منزل', 'price' => 70_000, 'free_over' => 2_000_000, 'position' => 1],
            ['name' => 'پیک موتوری (تهران)', 'description' => 'تحویل همان‌روز در تهران', 'price' => 90_000, 'free_over' => null, 'position' => 2],
            ['name' => 'تحویل حضوری', 'description' => 'دریافت از فروشگاه', 'price' => 0, 'free_over' => null, 'position' => 3],
        ];

        foreach ($methods as $method) {
            ShippingMethod::create($method + ['is_active' => true]);
        }
    }
}
