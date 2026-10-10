<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'recipient_name', 'phone', 'province', 'city',
        'postal_code', 'line', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Snapshot array stored on the order at purchase time. */
    public function toSnapshot(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'province' => $this->province,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'line' => $this->line,
        ];
    }
}
