<?php

namespace App\Support\Blocks;

use App\Models\Category;
use App\Models\Collection as ProductCollection;
use Illuminate\Support\Facades\Schema;

/**
 * Option lists for searchable picker fields in the page builder, so the admin
 * selects real categories/collections instead of typing slugs.
 *
 * @return array<string,string>  value => label
 */
class BlockPicker
{
    /** @return array<string,string> */
    public static function options(string $source): array
    {
        return match ($source) {
            'product_feed' => self::productFeed(),
            'categories' => self::categories(),
            'collections' => self::collections(),
            'products' => self::products(),
            default => [],
        };
    }

    /** @return array<string,string>  slug => name */
    private static function products(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        return \App\Models\Product::active()->orderByDesc('id')->limit(500)
            ->pluck('name', 'slug')->all();
    }

    /** @return array<string,string> */
    private static function productFeed(): array
    {
        $opts = [
            'featured' => 'محصولات منتخب',
            'new' => 'جدیدترین‌ها',
        ];
        foreach (self::categories() as $slug => $name) {
            $opts['category:'.$slug] = 'دسته: '.$name;
        }
        foreach (self::collections() as $slug => $name) {
            $opts['collection:'.$slug] = 'مجموعه: '.$name;
        }

        return $opts;
    }

    /** @return array<string,string> */
    private static function categories(): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }

        return Category::where('is_active', true)->orderBy('position')
            ->pluck('name', 'slug')->all();
    }

    /** @return array<string,string> */
    private static function collections(): array
    {
        if (! Schema::hasTable('collections')) {
            return [];
        }

        return ProductCollection::where('is_active', true)->orderBy('position')
            ->pluck('name', 'slug')->all();
    }
}
