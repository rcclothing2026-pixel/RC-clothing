<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $fillable = ['email', 'name', 'unsubscribe_token', 'subscribed_at'];

    protected function casts(): array
    {
        return ['subscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sub) {
            $sub->unsubscribe_token = $sub->unsubscribe_token ?: Str::random(60);
            $sub->subscribed_at = $sub->subscribed_at ?: now();
        });
    }
}
