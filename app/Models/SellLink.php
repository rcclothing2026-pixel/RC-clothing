<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A purchase link an admin sends on Instagram: /b/{code} fills the customer's cart. */
class SellLink extends Model
{
    protected $fillable = [
        'code', 'created_by', 'items', 'coupon_code', 'customer_handle',
        'expires_at', 'opened_at', 'open_count', 'order_id',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'expires_at' => 'datetime',
            'opened_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Short, unambiguous code (no 0/O/1/I/L). */
    public static function newCode(): string
    {
        do {
            $code = collect(range(1, 5))
                ->map(fn () => '23456789ABCDEFGHJKMNPQRSTUVWXYZ'[random_int(0, 30)])
                ->implode('');
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function url(): string
    {
        return route('sell.show', $this->code);
    }

    /** sent | opened | ordered | paid | expired — for the admin's «لینک‌های من» list. */
    public function status(): string
    {
        if ($this->order?->isPaid()) {
            return 'paid';
        }
        if ($this->order) {
            return 'ordered';
        }
        if ($this->isExpired()) {
            return 'expired';
        }

        return $this->opened_at ? 'opened' : 'sent';
    }

    /** Total item units in the link. */
    public function unitCount(): int
    {
        return (int) collect($this->items)->sum('qty');
    }
}
