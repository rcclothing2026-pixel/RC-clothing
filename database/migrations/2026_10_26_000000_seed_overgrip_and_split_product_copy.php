<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copy for the products added after the 2026-10-16 seed: the Racket Club
 * overgrips (single + 12-pack) and the per-colour splits of the Pixel and
 * Imperial tees. The splits were duplicated with the two-colour text, so these
 * rows are written in full (Persian + English). Missing slugs are skipped.
 */
return new class extends Migration
{
    private const CARE = [
        'cotton' => [
            'fa' => ['شستشو در دمای حداکثر ۳۰ درجه، پشت‌ورو', 'بدون سفیدکننده؛ خشک‌کن استفاده نشود', 'اتو با حرارت کم از پشت، هرگز روی چاپ یا گلدوزی'],
            'en' => ['Machine wash at 30°C max, inside out', 'No bleach; do not tumble dry', 'Low iron on the reverse, never over print or embroidery'],
        ],
        'grip' => [
            'fa' => ['اورگریپ را با کشش ملایم و هم‌پوشانی یکنواخت روی گریپ اصلی بپیچید', 'در جای خشک و دور از نور مستقیم آفتاب نگه دارید', 'وقتی سطح آن صاف یا ساییده شد، تعویضش کنید'],
            'en' => ['Wrap over the base grip with light tension and an even overlap', 'Store somewhere dry, away from direct sunlight', 'Replace once the surface turns smooth or worn'],
        ],
    ];

    private function grip(string $type, bool $pack, string $faColour, string $enColour): array
    {
        $dry = $type === 'dry';
        $qtyFa = $pack ? 'بسته ۱۲ عددی' : '۱ عدد';
        $qtyEn = $pack ? '12-pack' : 'single';
        $model = $dry ? 'Super Dry' : 'Super Tacky';

        return [
            "Racket Club {$model} Overgrip — ".($pack ? '12-Pack' : 'Single'), 'grip',
            'fa' => [
                $dry
                    ? "اورگریپ Super Dry راکت کلاب ({$qtyFa})؛ حس خشک و نرم با جذب بالای عرق، ضدلغزش و بادوام برای راکت پدل و تنیس."
                    : "اورگریپ Super Tacky راکت کلاب ({$qtyFa})؛ سطح چسبنده برای کنترل حداکثری، ضدلغزش، جاذب عرق و بادوام برای راکت پدل و تنیس.",
                $dry
                    ? "اورگریپ Super Dry <strong>راکت کلاب</strong> برای بازیکنانی است که دستشان در بازی عرق می‌کند. سطح نرم و خشک آن رطوبت را سریع جذب می‌کند تا راکت پدل یا تنیس در ست‌های طولانی هم محکم و مطمئن در دستتان بماند. بادوام، ضدلغزش و با شعار راکت کلاب: Play longer. Hit cleaner."
                    : "اورگریپ Super Tacky <strong>راکت کلاب</strong> برای بازیکنانی است که دقت و کنترل می‌خواهند. سطح چسبنده (تکی) آن دست را روی دسته راکت پدل یا تنیس قفل می‌کند و حسی محکم و مطمئن در هر ضربه می‌دهد؛ در عین حال عرق را جذب می‌کند و بادوام و ضدلغزش است. Play longer. Hit cleaner.",
                array_values(array_filter([
                    "مدل: {$model}",
                    $dry ? 'حس خشک و نرم، جذب بالای رطوبت' : 'سطح چسبنده (Tacky) برای کنترل حداکثری',
                    $dry ? 'ویژگی‌ها: جاذب عرق، بادوام، ضدلغزش' : 'ویژگی‌ها: چسبنده، جاذب عرق، بادوام، ضدلغزش',
                    'مناسب راکت پدل و تنیس',
                    "تعداد: {$qtyFa}",
                    "رنگ: {$faColour}",
                    $pack ? 'بسته‌بندی کیسه‌ای راکت کلاب، به‌صرفه برای بازیکنان حرفه‌ای و باشگاه‌ها' : null,
                ])),
            ],
            'en' => [
                $dry
                    ? "Racket Club Super Dry overgrip ({$qtyEn}): a soft, dry feel with high sweat absorption, anti-slip and durable, for padel and tennis rackets."
                    : "Racket Club Super Tacky overgrip ({$qtyEn}): a high-tack surface for maximum control, anti-slip, absorbent and durable, for padel and tennis rackets.",
                $dry
                    ? "The <strong>Racket Club</strong> Super Dry overgrip is made for players whose hands sweat. Its soft, dry surface absorbs moisture fast, so your padel or tennis racket stays secure and comfortable through long matches. Durable, anti-slip and true to the Racket Club line: Play longer. Hit cleaner."
                    : "The <strong>Racket Club</strong> Super Tacky overgrip is made for players who want precision and control. Its high-tack surface locks your hand onto the padel or tennis handle for a firm, confident feel on every shot, while still absorbing sweat. Durable and anti-slip. Play longer. Hit cleaner.",
                array_values(array_filter([
                    "Model: {$model}",
                    $dry ? 'Soft, dry feel with high moisture absorption' : 'High-tack surface for maximum control',
                    $dry ? 'Absorbent, durable, anti-slip' : 'Tacky, absorbent, durable, anti-slip',
                    'For padel and tennis rackets',
                    'Quantity: '.($pack ? '12 overgrips' : '1 overgrip'),
                    "Colour: {$enColour}",
                    $pack ? 'Racket Club pouch, good value for regular players and clubs' : null,
                ])),
            ],
        ];
    }

    private function pixel(string $faColour, string $enColour): array
    {
        return [
            'Racket Club × Chiaco Palm & Tennis Pixel T-Shirt — '.$enColour, 'cotton',
            'fa' => [
                "تی‌شرت نخل و تنیس پیکسل {$faColour} از همکاری راکت کلاب و چیاکو؛ ۱۰۰٪ نخ پنبه، اورسایز، با طرح پیکسلی زمین خاکی و نخل‌ها پشت. تولید ایران.",
                "تی‌شرت نخل و تنیس پیکسل از کالکشن مشترک <strong>راکت کلاب</strong> و چیاکو است: طرح بزرگ پیکسلی یک زمین خاکی تنیس میان نخل‌ها، با نوشته Racket Club × Chiaco، پشت تی‌شرت و نشان گرد Chiaco × Racket Club روی سینه. پارچه ۱۰۰٪ نخ پنبه و فرم اورسایز با سرشانه افتاده، آن را به یکی از شاخص‌ترین تی‌شرت‌های راکت کلاب تبدیل کرده است.",
                ['جنس: ۱۰۰٪ نخ پنبه', 'طرح پیکسلی زمین تنیس و نخل‌ها پشت؛ نشان گرد Chiaco × Racket Club روی سینه', 'اجرا با چاپ سیلک سه‌بعدی و گلدوزی', 'فرم اورسایز با سرشانه افتاده', "رنگ: {$faColour}", 'سایزها: S تا XXL', 'تولید ایران'],
            ],
            'en' => [
                "Racket Club × Chiaco Palm & Tennis Pixel tee in {$enColour}: 100% cotton, oversized, with a pixel-art clay court and palms on the back. Made in Iran.",
                "The Palm & Tennis Pixel tee comes from the <strong>Racket Club</strong> × Chiaco collaboration: a large pixel-art clay tennis court framed by palms, signed Racket Club × Chiaco, across the back, and a round Chiaco × Racket Club badge on the chest. 100% cotton and an oversized, dropped-shoulder fit make it one of Racket Club's signature tees.",
                ['Fabric: 100% cotton', 'Pixel-art tennis court and palms on the back; round Chiaco × Racket Club chest badge', 'Finished in 3D silk print and embroidery', 'Oversized fit with dropped shoulders', "Colour: {$enColour}", 'Sizes: S to XXL', 'Made in Iran'],
            ],
        ];
    }

    private function products(): array
    {
        return [
            'aadd-aorgryp-rakt-klab-super-dry-1482' => $this->grip('dry', false, 'آبی', 'blue'),
            'aorgryp-rakt-klab-super-dry-bsth-aaddy-1483' => $this->grip('dry', true, 'آبی', 'blue'),
            'aadd-aorgryp-rakt-klab-super-tacky-1480' => $this->grip('tacky', false, 'مشکی', 'black'),
            'aorgryp-rakt-klab-super-tacky-1481' => $this->grip('tacky', true, 'مشکی', 'black'),
            'nkhl-o-tnys-765' => $this->pixel('مشکی', 'Black'),
            'nkhl-o-tnys-pyksl-khohay-1474' => $this->pixel('قهوه‌ای', 'Brown'),
            'nkhl-o-tnys-pyksl-krm-1475' => $this->pixel('کرم', 'Cream'),
            'tyshrt-imperial-1479' => [
                'Racket Club Imperial T-Shirt — Green', 'cotton',
                'fa' => [
                    'تی‌شرت Imperial سبز راکت کلاب؛ ۱۰۰٪ نخ پنبه با طرح زمین پدل Imperial Country Club پشت و لوگوی راکت کلاب روی سینه. تولید ایران.',
                    'تی‌شرت Imperial ادای احترام <strong>راکت کلاب</strong> به باشگاه‌های کانتری کلاب است. پشت آن طرح خطی زمین پدل با بازیکنان و نوشته Imperial Country Club آمده و روی سینه لوگوی کوچک راکت کلاب نشسته است. پارچه ۱۰۰٪ نخ پنبه و فرم راحت، آن را برای بازی دوستانه و استایل روزمره مناسب می‌کند.',
                    ['جنس: ۱۰۰٪ نخ پنبه', 'طرح زمین پدل و Imperial Country Club پشت؛ لوگوی راکت کلاب روی سینه', 'اجرا با چاپ سیلک سه‌بعدی و گلدوزی', 'رنگ: سبز', 'سایزها: S تا XXL', 'تولید ایران'],
                ],
                'en' => [
                    'Racket Club Imperial tee in green: 100% cotton, an Imperial Country Club padel-court artwork on the back and the Racket Club logo on the chest. Made in Iran.',
                    'The Imperial tee is <strong>Racket Club</strong>\'s nod to the classic country club. The back carries a line drawing of a padel court in play with Imperial Country Club script, and the small Racket Club logo sits on the chest. 100% cotton and an easy fit make it right for a friendly match and every day.',
                    ['Fabric: 100% cotton', 'Imperial Country Club padel-court artwork on the back; Racket Club logo on the chest', 'Finished in 3D silk print and embroidery', 'Colour: green', 'Sizes: S to XXL', 'Made in Iran'],
                ],
            ],
        ];
    }

    private function html(string $intro, array $features, array $care, string $featuresTitle, string $careTitle): string
    {
        $li = fn (array $items) => '<ul>'.implode('', array_map(fn ($i) => '<li>'.e($i).'</li>', $items)).'</ul>';

        return '<p>'.$intro.'</p><h3>'.$featuresTitle.'</h3>'.$li($features).'<h3>'.$careTitle.'</h3>'.$li($care);
    }

    public function up(): void
    {
        foreach ($this->products() as $slug => $p) {
            $care = self::CARE[$p[1]];
            $grip = $p[1] === 'grip';
            DB::table('products')->where('slug', $slug)->update([
                'name_en' => $p[0],
                'summary' => $p['fa'][0],
                'summary_en' => $p['en'][0],
                'description' => $this->html($p['fa'][1], $p['fa'][2], $care['fa'], 'مشخصات', $grip ? 'نصب و نگهداری' : 'شستشو و نگهداری'),
                'description_en' => $this->html($p['en'][1], $p['en'][2], $care['en'], 'Details', $grip ? 'Fitting and care' : 'Care'),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Content seed — nothing to undo structurally.
    }
};
