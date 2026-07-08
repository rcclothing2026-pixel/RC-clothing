<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One child line of a gift bundle: a (variant, quantity) included in a parent
 * bundle product. See migration create_bundles for the table shape.
 */
class BundleItem extends Model
{
    protected $fillable = ['bundle_product_id', 'product_variant_id', 'quantity', 'position'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'position' => 'integer',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
