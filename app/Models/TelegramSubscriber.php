<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSubscriber extends Model
{
    protected $fillable = ['chat_id', 'first_name', 'username', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function label(): string
    {
        return trim(($this->first_name ?? '').($this->username ? ' @'.$this->username : '')) ?: $this->chat_id;
    }
}
