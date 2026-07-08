<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    protected $fillable = [
        'category_id', 'collection_id', 'size_guide_id', 'brand', 'name', 'slug', 'summary', 'description',
        'price', 'compare_at_price', 'is_active', 'is_featured', 'is_bundle',
        'stockkeeping_id', 'stock_synced_at', 'broadcast_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_bundle' => 'boolean',
            'stock_synced_at' => 'datetime',
            'broadcast_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** All categories this product belongs to (the primary lives in category_id). */
    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function sizeGuide(): BelongsTo
    {
        return $this->belongsTo(SizeGuide::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /** Sold line items — powers the «پرفروش» (bestseller) shop sort. */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** Primary image path, falling back to the first image. */
    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->primaryImage()?->path;
    }

    /** Child items of a gift bundle (variant + quantity per parent bundle product). */
    public function bundleItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_product_id')->orderBy('position');
    }

    /**
     * How many of this bundle can be assembled right now from current child stock.
     * = min over children of floor(child.stock_qty / item.quantity). Zero if any
     * child is sold out or inactive, or if the bundle has no items configured.
     */
    public function bundleAvailableQty(): int
    {
        if (! $this->is_bundle) {
            return PHP_INT_MAX;
        }
        $items = $this->relationLoaded('bundleItems') ? $this->bundleItems : $this->bundleItems()->with('variant')->get();
        if ($items->isEmpty()) {
            return 0;
        }
        $caps = $items->map(function (BundleItem $i) {
            $v = $i->variant;
            if (! $v || ! $v->is_active || $i->quantity < 1) {
                return 0;
            }
            return intdiv(max(0, (int) $v->stock_qty), max(1, (int) $i->quantity));
        });
        return (int) $caps->min();
    }

    /** Sum of cached variant stock (authoritative source is stock-keeping). */
    public function totalStock(): int
    {
        if ($this->is_bundle) {
            return $this->bundleAvailableQty();
        }
        return (int) $this->variants->sum('stock_qty');
    }

    public function inStock(): bool
    {
        return $this->totalStock() > 0;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function discountPercent(): int
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        return (int) round((1 - $this->price / $this->compare_at_price) * 100);
    }

    public function formattedPrice(): string
    {
        return Money::toman($this->price);
    }

    public function formattedCompareAtPrice(): string
    {
        return Money::toman($this->compare_at_price);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'price' => (int) $this->price,
            'category_id' => (int) $this->category_id,
            'category_ids' => $this->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all(),
            'collection_id' => (int) $this->collection_id,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at?->timestamp ?? 0,
            'variants' => $this->variants->map(fn ($v) => [
                'sku' => $v->sku,
                'size' => $v->size,
                'color' => $v->color,
                'price' => (int) ($v->price ?? $this->price),
            ]),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active;
    }
}
