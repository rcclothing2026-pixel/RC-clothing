<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'image_path', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Keep an editable brick/masonry page in sync with the collection
     * lifecycle: build one on create, tear it down (with its menu items) on
     * delete. Mirrors Category.
     */
    protected static function booted(): void
    {
        static::created(function (self $c) {
            if (! empty($c->slug)) {
                Page::brickForCatalog('collection', $c->slug, $c->name);
            }
        });
        static::deleted(function (self $c) {
            if (! empty($c->slug)) {
                Page::removeBrick('collection', $c->slug);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
