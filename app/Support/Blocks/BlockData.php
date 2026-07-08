<?php

namespace App\Support\Blocks;

use App\Models\Category;
use App\Models\Collection as ProductCollection;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Resolves the live storefront data a dynamic block needs at render time
 * (products, categories). Keeps the block partials free of queries.
 */
class BlockData
{
    /** @param array<string,mixed> $data */
    public static function products(array $data): Collection
    {
        $limit = max(1, min(24, (int) ($data['limit'] ?? 8)));
        $query = Product::active()->with(['images', 'variants']);

        // Explicit product selection (search-and-pick) takes precedence over the feed.
        $picked = array_values(array_filter(array_map('trim', explode(',', (string) ($data['products'] ?? '')))));
        if ($picked) {
            return $query->whereIn('slug', $picked)->get()
                ->sortBy(fn ($p) => array_search($p->slug, $picked, true))
                ->values()
                ->take($limit);
        }

        // New searchable picker: feed = featured|new|category:slug|collection:slug.
        // Falls back to the legacy source/ref pair for older saved blocks.
        $feed = trim((string) ($data['feed'] ?? ''));
        if ($feed !== '') {
            if (str_starts_with($feed, 'category:')) {
                $source = 'category';
                $ref = substr($feed, 9);
            } elseif (str_starts_with($feed, 'collection:')) {
                $source = 'collection';
                $ref = substr($feed, 11);
            } else {
                $source = $feed; // featured | new
                $ref = '';
            }
        } else {
            $source = $data['source'] ?? 'featured';
            $ref = trim((string) ($data['ref'] ?? ''));
        }

        switch ($source) {
            case 'new':
                $query->latest();
                break;
            case 'category':
                $query->whereHas('category', fn ($q) => $q->where('slug', $ref))->latest();
                break;
            case 'collection':
                $query->whereHas('collection', fn ($q) => $q->where('slug', $ref))->latest();
                break;
            case 'manual':
                $ids = array_filter(array_map('intval', explode(',', $ref)));
                if (! $ids) {
                    return collect();
                }
                $query->whereIn('id', $ids)->orderByRaw('FIELD(id,'.implode(',', $ids).')');
                break;
            case 'featured':
            default:
                $query->featured()->latest();
                break;
        }

        return $query->take($limit)->get();
    }

    /** @param array<string,mixed> $data */
    public static function categories(array $data): Collection
    {
        $limit = (int) ($data['limit'] ?? 0);
        $ref = trim((string) ($data['ref'] ?? ''));

        // Explicitly picked categories show in the order they were chosen.
        if ($ref !== '') {
            $slugs = array_values(array_filter(array_map('trim', explode(',', $ref))));
            $cats = Category::where('is_active', true)->whereIn('slug', $slugs)->get()
                ->sortBy(fn ($c) => array_search($c->slug, $slugs, true))
                ->values();

            return $limit > 0 ? $cats->take($limit) : $cats;
        }

        $query = Category::where('is_active', true)->orderBy('position');
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Active collections for the collection-scroller block. Optional `ref`
     * (comma-separated slugs) picks specific ones; otherwise all by position.
     *
     * @param array<string,mixed> $data
     * @return Collection<int, ProductCollection>
     */
    public static function collections(array $data): Collection
    {
        $query = ProductCollection::where('is_active', true)->orderBy('position');
        $ref = trim((string) ($data['ref'] ?? ''));
        if ($ref !== '') {
            $slugs = array_filter(array_map('trim', explode(',', $ref)));
            $query->whereIn('slug', $slugs);
        }
        $limit = (int) ($data['limit'] ?? 0);
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public static function collection(string $slug): ?ProductCollection
    {
        return ProductCollection::where('slug', $slug)->where('is_active', true)->first();
    }
}
