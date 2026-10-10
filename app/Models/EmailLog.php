<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = ['type', 'recipient_email', 'subject', 'status', 'error', 'relatable_type', 'relatable_id'];

    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }
}
