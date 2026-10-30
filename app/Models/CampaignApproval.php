<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignApproval extends Model
{
    protected $fillable = ['key', 'starts_on', 'ends_on', 'approved', 'approved_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'approved' => 'boolean'];
    }
}
