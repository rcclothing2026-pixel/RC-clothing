<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed navigation links for the header and the two footer columns.
 * Cached so the header/footer render cheaply; the cache is busted on save.
 */
class MenuItem extends Model
{
    protected $fillable = ['location', 'parent_id', 'label', 'url', 'image', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Normalise internal links to a host-relative path so they work on any
     * host / port / domain. This fixes seeded `http://localhost/...` URLs that
     * break under `php artisan serve --port=XXXX` (the port is missing) and makes
     * the nav domain-agnostic. External links are returned unchanged.
     */
    public function getUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! $host) {
            return $value; // already relative
        }

        $internal = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            'localhost',
            '127.0.0.1',
        ]);

        if (! in_array($host, $internal, true)) {
            return $value; // external link — keep absolute
        }

        $path = parse_url($value, PHP_URL_PATH) ?: '/';
        $query = parse_url($value, PHP_URL_QUERY);
        $fragment = parse_url($value, PHP_URL_FRAGMENT);

        return $path.($query ? '?'.$query : '').($fragment ? '#'.$fragment : '');
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /** Locations and their admin labels. */
    public const LOCATIONS = [
        'header' => 'منوی بالای سایت',
        'footer_1' => 'فوتر — دسترسی سریع',
        'footer_2' => 'فوتر — خدمات مشتریان',
    ];

    private const CACHE_KEY = 'menu_items:active';

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }

    /**
     * Active items for a location, ordered. Returns an empty collection when no
     * menu has been defined, so callers can fall back to their defaults.
     *
     * @return \Illuminate\Support\Collection<int, MenuItem>
     */
    public static function for(string $location): \Illuminate\Support\Collection
    {
        // Cache plain rows (not Eloquent/Support Collection) so we don't depend
        // on serialised class shapes across deploys.
        $loader = fn () => \Illuminate\Support\Facades\Schema::hasTable('menu_items')
            ? static::query()->where('is_active', true)
                ->orderBy('position')->orderBy('id')
                ->get()->map->getAttributes()->all()
            : [];

        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, $loader);
        } catch (\Throwable $e) {
            // A pre-fix cached value (Support/Eloquent Collection) can blow up
            // on unserialize; rebuild from source and reset the cache.
            Cache::forget(self::CACHE_KEY);
            $rows = $loader();
            Cache::forever(self::CACHE_KEY, $rows);
        }

        if (! is_array($rows)) {
            // Defensive: any non-array (e.g. a legacy Collection) → refresh.
            Cache::forget(self::CACHE_KEY);
            $rows = $loader();
            Cache::forever(self::CACHE_KEY, $rows);
        }

        $items = collect($rows)
            ->where('location', $location)
            ->map(fn (array $attrs) => (new static)->setRawAttributes($attrs, true));

        // Build a one-level tree: top-level items each carry their children.
        $childrenByParent = $items->filter(fn ($i) => $i->parent_id)->groupBy('parent_id');

        return $items
            ->filter(fn ($i) => ! $i->parent_id)
            ->map(function (MenuItem $item) use ($childrenByParent) {
                $item->setRelation('children', ($childrenByParent[$item->id] ?? collect())->values());

                return $item;
            })
            ->values();
    }
}
