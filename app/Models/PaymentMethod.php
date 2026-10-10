<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'key', 'label', 'description', 'is_active', 'is_default', 'sandbox', 'config', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sandbox' => 'boolean',
            'config' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /** Field schema per gateway for the admin settings form. */
    public static function fieldsFor(string $key): array
    {
        return match ($key) {
            'zarinpal' => ['merchant_id' => 'مرچنت کد (Merchant ID)'],
            'zibal' => ['merchant' => 'مرچنت (Merchant)'],
            'snapppay' => [
                'base_url' => 'آدرس پایه (Base URL)',
                'client_id' => 'Client ID',
                'client_secret' => 'Client Secret',
                'username' => 'نام کاربری مرچنت',
                'password' => 'رمز عبور مرچنت',
            ],
            default => [],
        };
    }
}
