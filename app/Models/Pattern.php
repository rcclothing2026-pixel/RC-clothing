<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable block pattern. Stores one or more block-definitions (the same
 * JSON shape pages use) under a name; the page editor surfaces them as
 * drag-cards in the tray.
 */
class Pattern extends Model
{
    protected $fillable = ['name', 'icon', 'blocks', 'created_by'];

    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
