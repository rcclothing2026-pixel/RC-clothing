<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedCart extends Model
{
    protected $fillable = ['user_id', 'token', 'items', 'reminded_at'];

    protected function casts(): array
    {
        return ['items' => 'array', 'reminded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
