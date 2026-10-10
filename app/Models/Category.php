<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image_path', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Keep an editable brick/masonry page in sync with the category lifecycle:
     * build one on create, tear it down (with its menu items) on delete.
     */
    protected static function booted(): void
    {
        static::created(function (self $c) {
            if (! empty($c->slug)) {
                Page::brickForCatalog('category', $c->slug, $c->name);
            }
        });
        static::deleted(function (self $c) {
            if (! empty($c->slug)) {
                Page::removeBrick('category', $c->slug);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
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
