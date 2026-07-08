<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Polos', 'slug' => 'polos'],
            ['name' => 'Tees', 'slug' => 'tees'],
            ['name' => 'Shorts', 'slug' => 'shorts'],
            ['name' => 'Knitwear', 'slug' => 'knitwear'],
            ['name' => 'Outerwear', 'slug' => 'outerwear'],
            ['name' => 'Accessories', 'slug' => 'accessories'],
        ];

        $catModels = [];
        foreach ($categories as $i => $c) {
            $catModels[$c['slug']] = Category::create([
                'name' => $c['name'],
                'slug' => $c['slug'],
                'position' => $i,
                'is_active' => true,
                'image_path' => '/placeholder?w=600&h=600&seed='.$c['slug'].'&label='.urlencode($c['name']),
            ]);
        }

        // Racket Club — quiet-luxury leisurewear demo catalogue. English names,
        // brand-palette colours, prices in Toman. Where a verified campaign photo
        // matches the piece it's used as the primary image (admin-swappable).
        // [name, category slug, price(Toman), compareAt|null, featured, colors, heroPhoto|null]
        $products = [
            ['Heritage Piqué Polo', 'polos', 1_280_000, 1_650_000, true, ['Cream' => '#ece7d0', 'Navy' => '#18234f', 'Clay' => '#a72f23'], null],
            ['Club Long-Sleeve Polo', 'polos', 1_460_000, null, false, ['Forest' => '#034326', 'Sand' => '#c6af92'], null],
            ['Essential Boxy Tee', 'tees', 640_000, 820_000, true, ['Cream' => '#ece7d0', 'Navy' => '#18234f', 'Forest' => '#034326'], '/img/photography/rc-trio-navy.jpg'],
            ['Legends Graphic Tee', 'tees', 720_000, null, true, ['Forest' => '#034326', 'Black' => '#141414'], '/img/photography/rc-padel-back.jpg'],
            ['Off-Court Pleated Short', 'shorts', 980_000, null, true, ['Cream' => '#ece7d0', 'Navy' => '#18234f'], null],
            ['Terry Sweat Short', 'shorts', 860_000, 1_040_000, false, ['Sand' => '#c6af92', 'Navy' => '#18234f'], null],
            ['Cotton Cable Knit', 'knitwear', 1_980_000, null, false, ['Cream' => '#ece7d0', 'Forest' => '#034326'], null],
            ['Merino Half-Zip', 'knitwear', 2_240_000, 2_600_000, true, ['Navy' => '#18234f', 'Sand' => '#c6af92'], null],
            ['The Clubhouse Overshirt', 'outerwear', 2_480_000, null, false, ['Forest' => '#034326', 'Navy' => '#18234f'], null],
            ['Warm-Up Track Jacket', 'outerwear', 2_780_000, 3_200_000, true, ['Navy' => '#18234f', 'Clay' => '#a72f23'], null],
            ['Ribbed Crew Socks', 'accessories', 240_000, 320_000, false, ['Navy' => '#18234f', 'Cream' => '#ece7d0'], '/img/photography/rc-socks-stack.jpg'],
            ['Legacy Canvas Tote', 'accessories', 460_000, null, true, ['Sand' => '#c6af92', 'Navy' => '#18234f'], '/img/photography/rc-kneel-tote.jpg'],
        ];

        $sizes = ['S', 'M', 'L', 'XL'];

        foreach ($products as $idx => [$name, $catSlug, $price, $compare, $featured, $colors, $heroPhoto]) {
            $slug = Str::slug(Str::ascii($catSlug).'-'.($idx + 1)).'-'.Str::random(4);

            $product = Product::create([
                'category_id' => $catModels[$catSlug]->id,
                'name' => $name,
                'slug' => $slug,
                'summary' => 'Considered fabric, a clean finish, and an easy modern fit.',
                'description' => "<p>{$name} — cut from considered fabric with a clean, durable finish. Made for the life off the court: the slow mornings, the long lunches, the easy evenings.</p><ul><li>Premium, breathable cloth</li><li>Relaxed, modern fit</li><li>Machine washable, cold</li></ul>",
                'price' => $price,
                'compare_at_price' => $compare,
                'is_active' => true,
                'is_featured' => $featured,
                'stockkeeping_id' => null, // mapped on first sync with stock-keeping
            ]);

            // Images: a verified campaign photo as the primary where one matches,
            // then a colour-tinted placeholder per remaining colour. All rows are
            // editable in admin (Media library / product images), so they swap.
            $pos = 0;
            foreach ($colors as $colorName => $hex) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => ($pos === 0 && $heroPhoto)
                        ? $heroPhoto
                        : '/placeholder?w=800&h=1000&seed='.$slug.$colorName.'&label='.urlencode($name),
                    'alt' => $name.' - '.$colorName,
                    'position' => $pos,
                    'is_primary' => $pos === 0,
                ]);
                $pos++;
            }

            // Variants: size × color
            foreach ($colors as $colorName => $hex) {
                foreach ($sizes as $size) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size' => $size,
                        'color' => $colorName,
                        'color_hex' => $hex,
                        'sku' => strtoupper(Str::random(3)).'-'.($product->id).'-'.$size,
                        'stock_qty' => random_int(0, 12),
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
