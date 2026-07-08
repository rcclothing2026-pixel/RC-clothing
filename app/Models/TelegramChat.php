<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Telegram private chat the bot knows about, with a role that decides how the
 * bot treats it (admin ops, a linked customer, or an unassigned pending chat).
 */
class TelegramChat extends Model
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_PENDING = 'pending';

    protected $fillable = [
        'chat_id', 'user_id', 'role', 'first_name', 'username', 'is_active', 'linked_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'linked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAdmins(Builder $q): Builder
    {
        return $q->where('role', self::ROLE_ADMIN)->where('is_active', true);
    }

    public function scopeCustomers(Builder $q): Builder
    {
        return $q->where('role', self::ROLE_CUSTOMER)->where('is_active', true);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('role', self::ROLE_PENDING);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN && $this->is_active;
    }

    /** Display name for admin lists: "First @username" or the chat id. */
    public function label(): string
    {
        return trim(($this->first_name ?? '').($this->username ? ' @'.$this->username : '')) ?: $this->chat_id;
    }
}
