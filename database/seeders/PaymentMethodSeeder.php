<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'key' => 'zarinpal',
                'label' => 'زرین‌پال',
                'description' => 'پرداخت امن از طریق درگاه زرین‌پال',
                'is_active' => true,
                'is_default' => true,
                'sandbox' => true,
                'position' => 0,
                'config' => ['merchant_id' => null],
            ],
            [
                'key' => 'zibal',
                'label' => 'زیبال',
                'description' => 'پرداخت از طریق درگاه زیبال',
                'is_active' => false,
                'is_default' => false,
                'sandbox' => true,
                'position' => 1,
                'config' => ['merchant' => null],
            ],
            [
                'key' => 'snapppay',
                'label' => 'اسنپ‌پی (خرید قسطی)',
                'description' => 'خرید اعتباری و پرداخت قسطی با اسنپ‌پی',
                'is_active' => false,
                'is_default' => false,
                'sandbox' => true,
                'position' => 2,
                'config' => ['base_url' => null, 'client_id' => null, 'client_secret' => null, 'username' => null, 'password' => null],
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(['key' => $method['key']], $method);
        }
    }
}
