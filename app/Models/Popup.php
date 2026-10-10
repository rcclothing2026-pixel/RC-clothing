<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-built website pop-up. Holds raw HTML (authored in the admin pop-up
 * builder) plus display rules (trigger, frequency, page scope). The storefront
 * JS engine shows it and remembers dismissals per the frequency rule.
 */
class Popup extends Model
{
    protected $fillable = [
        'name', 'html', 'trigger', 'delay', 'frequency', 'pages', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'delay' => 'integer'];
    }

    public const TRIGGERS = [
        'load' => 'هنگام باز شدن صفحه',
        'delay' => 'بعد از چند ثانیه',
        'exit' => 'هنگام خروج (Exit Intent)',
        'scroll' => 'بعد از اسکرول صفحه',
    ];

    public const FREQUENCIES = [
        'always' => 'هر بار',
        'session' => 'یک‌بار در هر نشست',
        'daily' => 'یک‌بار در روز',
        'once' => 'فقط یک‌بار',
    ];

    public const PAGES = [
        'all' => 'همه صفحات',
        'home' => 'فقط صفحه اصلی',
    ];

    /**
     * Active pop-ups as a plain array for the storefront engine.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function activePayload(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('popups')) {
            return [];
        }

        return static::query()->where('is_active', true)->latest()->get()
            ->map(fn (self $p) => [
                'id' => $p->id,
                'html' => (string) $p->html,
                'trigger' => $p->trigger,
                'delay' => $p->delay,
                'frequency' => $p->frequency,
                'pages' => $p->pages,
            ])->all();
    }
}
