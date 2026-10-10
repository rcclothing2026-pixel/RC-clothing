<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable sizing table the admin creates and assigns to products. Content is
 * purified HTML shown in a modal on the product page.
 */
class SizeGuide extends Model
{
    protected $fillable = ['name', 'content', 'image_path', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
