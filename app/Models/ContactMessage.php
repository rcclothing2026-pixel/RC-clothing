<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message inside a support conversation. A "thread" is every row that
 * shares the same telegram_chat_id (or, for legacy form-only senders, the same
 * phone / email). direction='in' is from the customer; direction='out' is the
 * admin's reply.
 */
class ContactMessage extends Model
{
    public const SOURCE_TELEGRAM = 'telegram';
    public const SOURCE_FORM = 'form';
    public const SOURCE_PHONE = 'phone';

    public const DIR_IN = 'in';
    public const DIR_OUT = 'out';

    protected $fillable = [
        'name', 'phone', 'email', 'message', 'is_read',
        'telegram_chat_id', 'source', 'direction', 'admin_user_id',
    ];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Every message in the same conversation as $this, oldest-first. Grouping
     * key is telegram_chat_id when present, otherwise the phone, otherwise the
     * email — so legacy form-only senders still get a unified thread.
     */
    public function threadMessages(): Collection
    {
        return static::query()->where(function (Builder $q) {
            if ($this->telegram_chat_id) {
                $q->where('telegram_chat_id', $this->telegram_chat_id);
            } elseif ($this->phone) {
                $q->where('phone', $this->phone)->whereNull('telegram_chat_id');
            } elseif ($this->email) {
                $q->where('email', $this->email)->whereNull('telegram_chat_id')->whereNull('phone');
            } else {
                $q->whereKey($this->id);
            }
        })->orderBy('created_at')->get();
    }

    /** A short identity label for inbox rows + Telegram alerts. */
    public function senderLabel(): string
    {
        return trim($this->name ?: '') ?: ($this->phone ?: ($this->email ?: ('Telegram #'.$this->telegram_chat_id)));
    }
}
