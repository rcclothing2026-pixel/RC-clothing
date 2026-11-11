<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class ProductVariant extends Model
{
    use Searchable;

    protected $fillable = [
        'product_id', 'size', 'color', 'color_hex', 'sku',
        'price', 'stock_qty', 'weight', 'is_active', 'stockkeeping_variant_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stoqs_size_price' => 'integer',
            'promo_price' => 'integer',
            'site_price_reported' => 'integer',
            'site_price_retry_at' => 'datetime',
            'stock_qty' => 'integer',
            'weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Effective price: variant override or the product's price. While StoqS manages this
     * site's promotions, a size whose StoqS window price differs from that carries it in
     * promo_price (StoqsPromotions::applyProduct); it is null otherwise.
     */
    public function effectivePrice(): int
    {
        if ($this->promo_price !== null && \App\Services\StockKeeping\StoqsPromotions::managed()) {
            return (int) $this->promo_price;
        }

        return (int) ($this->price ?? $this->product->price);
    }

    public function inStock(): bool
    {
        return $this->is_active && $this->availableQty() > 0;
    }

    /**
     * Buyable quantity. For a normal variant that's the cached stock_qty; for a
     * bundle's placeholder variant it's the bundle's computed availability (the
     * min over children of floor(child stock / per-bundle qty)). This lets the
     * cart clamp and stock badges use one method everywhere.
     */
    public function availableQty(): int
    {
        if ($this->product && $this->product->is_bundle) {
            return $this->product->bundleAvailableQty();
        }
        return (int) $this->stock_qty;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'sku' => $this->sku,
            'size' => $this->size,
            'color' => $this->color,
            'price' => (int) ($this->price ?? $this->product?->price ?? 0),
            'is_active' => $this->is_active,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active;
    }
}
