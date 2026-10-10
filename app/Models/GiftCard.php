<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    protected $fillable = ['code', 'initial_balance', 'balance', 'is_active', 'expires_at'];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'integer',
            'balance' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', mb_strtoupper(trim($code)))->first();
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && $this->balance > 0
            && (! $this->expires_at || now()->lte($this->expires_at));
    }
}
