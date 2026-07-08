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
            ['name' => 'مانتو', 'slug' => 'manto'],
            ['name' => 'پیراهن', 'slug' => 'pirahan'],
            ['name' => 'شلوار', 'slug' => 'shalvar'],
            ['name' => 'تیشرت و تاپ', 'slug' => 'tshirt'],
            ['name' => 'کت و پالتو', 'slug' => 'coat'],
            ['name' => 'شال و روسری', 'slug' => 'scarf'],
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

        // [name, category slug, price(Toman), compareAt|null, featured, colors]
        $products = [
            ['مانتو کتان جلو باز مدل آرامش', 'manto', 1_280_000, 1_650_000, true, ['مشکی' => '#1c1c1c', 'کرم' => '#e7ddca', 'زیتونی' => '#6b6f4b']],
            ['مانتو اداری مدل پاییز', 'manto', 1_540_000, null, true, ['طوسی' => '#8a8a8a', 'سرمه‌ای' => '#26324a']],
            ['پیراهن ویسکوز مدل نسیم', 'pirahan', 890_000, 1_120_000, true, ['آبی' => '#5b7aa6', 'صورتی' => '#d8a7b4']],
            ['پیراهن مجلسی مدل ماه', 'pirahan', 1_950_000, null, false, ['مشکی' => '#141414', 'شرابی' => '#5e1f2c']],
            ['شلوار پارچه‌ای دمپا', 'shalvar', 760_000, 920_000, false, ['مشکی' => '#161616', 'کرم' => '#e7ddca']],
            ['شلوار جین مام‌فیت', 'shalvar', 980_000, null, true, ['آبی روشن' => '#8fb0d6', 'آبی تیره' => '#33425c']],
            ['تیشرت نخی یقه گرد', 'tshirt', 320_000, 420_000, false, ['سفید' => '#f4f4f0', 'مشکی' => '#161616', 'سبز' => '#3f6b4f']],
            ['تاپ کبریتی بندی', 'tshirt', 280_000, null, false, ['کرم' => '#e7ddca', 'قهوه‌ای' => '#6b4f3a']],
            ['پالتو بلند مدل زمستان', 'coat', 2_780_000, 3_200_000, true, ['شتری' => '#c2a079', 'طوسی' => '#7f7f7f']],
            ['کت تک دکمه کلاسیک', 'coat', 2_240_000, null, false, ['سرمه‌ای' => '#26324a', 'مشکی' => '#141414']],
            ['شال نخی طرح‌دار', 'scarf', 240_000, 320_000, false, ['کرم' => '#e7ddca', 'آبی' => '#5b7aa6']],
            ['روسری ابریشمی مدل گل‌نقش', 'scarf', 360_000, null, true, ['صورتی' => '#d8a7b4', 'زرد' => '#d9c27a']],
        ];

        $sizes = ['S', 'M', 'L', 'XL'];

        foreach ($products as $idx => [$name, $catSlug, $price, $compare, $featured, $colors]) {
            $slug = Str::slug(Str::ascii($catSlug).'-'.($idx + 1)).'-'.Str::random(4);

            $product = Product::create([
                'category_id' => $catModels[$catSlug]->id,
                'name' => $name,
                'slug' => $slug,
                'summary' => 'کیفیت پارچه درجه یک، دوخت تمیز و فیت استاندارد.',
                'description' => "<p>{$name} با پارچه‌ای باکیفیت و دوخت اصولی، انتخابی مناسب برای استفاده روزمره و مجالس. شست‌وشوی آسان و ماندگاری رنگ بالا.</p><ul><li>جنس: پارچه باکیفیت ایرانی</li><li>دوخت: استاندارد</li><li>قابل شست‌وشو در ماشین لباسشویی با دمای پایین</li></ul>",
                'price' => $price,
                'compare_at_price' => $compare,
                'is_active' => true,
                'is_featured' => $featured,
                'stockkeeping_id' => null, // mapped on first sync with stock-keeping
            ]);

            // Images (placeholders, one per color tint)
            $pos = 0;
            foreach ($colors as $colorName => $hex) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => '/placeholder?w=800&h=1000&seed='.$slug.$colorName.'&label='.urlencode($name),
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
