<?php

namespace App\Support\Blocks;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Single source of truth for page-builder blocks. Every block type declares a
 * Persian label, an icon, and a field schema. The same definitions drive the
 * admin editor (which inputs to show), server-side validation (which keys are
 * allowed), and the default data used when a block is added.
 *
 * Field types: text | textarea | richtext | number | image | select | lines
 * | picker | multipicker (lines = one value per line, stored as an array;
 * picker = single search-select; multipicker = search + multi-select chips
 * stored as a comma-joined slug string).
 */
class BlockRegistry
{
    /** @var HTMLPurifier|null */
    private static $purifier = null;

    /** @return array<string, array{label:string, icon:string, fields:array}> */
    public static function all(): array
    {
        return [
            'hero_banner' => [
                'label' => 'بنر هیرو فروشگاه',
                'icon' => '🛒',
                'fields' => [], // edited in the dedicated Hero admin (admin → بنر هیرو)
            ],
            'motion_banner' => [
                'label' => 'بنر متحرک (اسلایدشو)',
                'icon' => '🎞',
                'fields' => [
                    ['key' => 'interval', 'label' => 'فاصله تعویض (ثانیه)', 'type' => 'number'],
                    ['key' => 'slides', 'label' => 'اسلایدها', 'type' => 'repeater', 'sub' => [
                        ['key' => 'title', 'label' => 'عنوان', 'type' => 'text'],
                        ['key' => 'subtitle', 'label' => 'زیرعنوان', 'type' => 'text'],
                        ['key' => 'image', 'label' => 'تصویر محصول', 'type' => 'image'],
                        ['key' => 'color', 'label' => 'رنگ بلاب', 'type' => 'select', 'options' => [
                            'purple' => 'بنفش', 'amber' => 'کهربایی', 'teal' => 'فیروزه‌ای', 'red' => 'قرمز چیاکو', 'dark' => 'تیره',
                        ]],
                        ['key' => 'cta_text', 'label' => 'متن دکمه', 'type' => 'text'],
                        ['key' => 'cta_link', 'label' => 'لینک دکمه', 'type' => 'text'],
                    ]],
                ],
            ],
            'hero' => [
                'label' => 'هیرو (سربرگ بزرگ)',
                'icon' => '🖼',
                'fields' => [
                    ['key' => 'badge', 'label' => 'برچسب کوچک', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'عنوان', 'type' => 'text'],
                    ['key' => 'accent', 'label' => 'بخش رنگی عنوان', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'زیرعنوان', 'type' => 'textarea'],
                    ['key' => 'cta_text', 'label' => 'متن دکمه', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'لینک دکمه', 'type' => 'text'],
                    ['key' => 'image', 'label' => 'تصویر', 'type' => 'image'],
                    ['key' => 'style', 'label' => 'پس‌زمینه', 'type' => 'select', 'options' => [
                        'light' => 'روشن', 'grad-red-light' => 'گرادیان قرمز', 'grad-dark-red' => 'گرادیان تیره‑قرمز', 'dark' => 'تیره',
                    ]],
                ],
            ],
            'product_grid' => [
                'label' => 'شبکه محصولات',
                'icon' => '🛍',
                'fields' => [
                    // Content
                    ['key' => 'heading', 'label' => 'عنوان بخش', 'type' => 'text'],
                    ['key' => 'products', 'label' => 'محصولات انتخابی (جستجو و انتخاب؛ خالی = از منبع زیر استفاده می‌شود)', 'type' => 'multipicker', 'source' => 'products'],
                    ['key' => 'feed', 'label' => 'منبع محصولات (وقتی محصول انتخابی ندارید)', 'type' => 'picker', 'source' => 'product_feed'],
                    ['key' => 'limit', 'label' => 'تعداد', 'type' => 'number'],
                    ['key' => 'shop_all_link', 'label' => 'لینک «مشاهده همه» (خالی = فروشگاه)', 'type' => 'text'],

                    // Layout mode
                    ['key' => 'layout', 'label' => 'چیدمان', 'type' => 'select', 'options' => [
                        'grid'     => 'شبکه (ستون‌بندی ثابت)',
                        'carousel' => 'اسلایدر افقی (سبک ادیتوریال)',
                    ]],

                    // Grid-mode column counts (per breakpoint)
                    ['key' => 'columns_mobile', 'label' => 'تعداد ستون — موبایل (شبکه)', 'type' => 'select', 'options' => [
                        '1' => '۱ ستون', '2' => '۲ ستون', '3' => '۳ ستون',
                    ]],
                    ['key' => 'columns_desktop', 'label' => 'تعداد ستون — دسکتاپ (شبکه)', 'type' => 'select', 'options' => [
                        '2' => '۲ ستون', '3' => '۳ ستون', '4' => '۴ ستون', '5' => '۵ ستون', '6' => '۶ ستون',
                    ]],

                    // Carousel-mode card size. Fullscreen was removed —
                    // it took over the entire viewport and broke editorial flow.
                    ['key' => 'card_size', 'label' => 'اندازه کارت (اسلایدر)', 'type' => 'select', 'options' => [
                        'compact' => 'فشرده — ۴–۵ کارت قابل دیدن',
                        'med'     => 'متوسط — ۳ کارت دسکتاپ، ۲ موبایل',
                        'large'   => 'بزرگ — ۲ کارت دسکتاپ، ۱.۲ موبایل',
                    ]],

                    // Card appearance
                    ['key' => 'card_style', 'label' => 'سبک کارت محصول', 'type' => 'select', 'options' => [
                        'minimal'  => 'مینیمال (عکس + نام + قیمت + دکمهٔ +)',
                        'detailed' => 'کامل (همراه با هاور، نشان، دکمه‌ها)',
                    ]],
                    ['key' => 'card_aspect', 'label' => 'نسبت تصویر کارت', 'type' => 'select', 'options' => [
                        'portrait' => 'پرتره ۳:۴ (پیش‌فرض)',
                        'tall'     => 'بلند ۲:۳',
                        'square'   => 'مربع ۱:۱',
                        'wide'     => 'افقی ۴:۳',
                    ]],

                    // Section spacing
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض (max-w-7xl)',
                        'wide'    => 'عریض (max-w-screen-2xl)',
                        'full'    => 'تمام‌عرض (لبه به لبه)',
                    ]],
                    ['key' => 'gap', 'label' => 'فاصلهٔ بین کارت‌ها', 'type' => 'select', 'options' => [
                        'tight'  => 'فشرده',
                        'normal' => 'معمولی (پیش‌فرض)',
                        'wide'   => 'بازتر',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'sm' => 'کم',
                        'md' => 'متوسط (پیش‌فرض)',
                        'lg' => 'زیاد',
                        'none' => 'بدون',
                    ]],
                ],
            ],
            'category_tiles' => [
                'label' => 'کاشی دسته‌بندی‌ها',
                'icon' => '🗂',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان بخش', 'type' => 'text'],
                    ['key' => 'ref', 'label' => 'دسته‌بندی‌ها (جستجو و انتخاب؛ خالی = همه)', 'type' => 'multipicker', 'source' => 'categories'],
                    ['key' => 'limit', 'label' => 'تعداد نمایش (خالی = همه)', 'type' => 'number'],
                    ['key' => 'fit', 'label' => 'نوع نمایش تصویر', 'type' => 'select', 'options' => [
                        'cover' => 'دایره با پس‌زمینه (عکس)',
                        'contain' => 'نمایش کامل بدون برش (PNG بدون پس‌زمینه و قاب)',
                    ]],
                    ['key' => 'layout', 'label' => 'چیدمان', 'type' => 'select', 'options' => [
                        'wrap' => 'شبکهٔ ثابت (پیش‌فرض)',
                        'marquee' => 'اسلایدر بی‌نهایت (Marquee)',
                    ]],
                    ['key' => 'speed', 'label' => 'سرعت اسلایدر (فقط در حالت Marquee)', 'type' => 'select', 'options' => [
                        'slow' => 'آرام',
                        'med' => 'متوسط',
                        'fast' => 'سریع',
                    ]],
                    ['key' => 'columns_mobile', 'label' => 'تعداد ستون — موبایل (شبکهٔ ثابت)', 'type' => 'select', 'options' => [
                        '2' => '۲', '3' => '۳', '4' => '۴',
                    ]],
                    ['key' => 'columns_desktop', 'label' => 'تعداد ستون — دسکتاپ (شبکهٔ ثابت)', 'type' => 'select', 'options' => [
                        '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸',
                    ]],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'collection_scroller' => [
                'label' => 'اسلایدر کالکشن‌ها (تصویر بزرگ)',
                'icon' => '🖼',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان بخش (اختیاری)', 'type' => 'text'],
                    ['key' => 'ref', 'label' => 'کالکشن‌ها (جستجو و انتخاب؛ خالی = همه)', 'type' => 'multipicker', 'source' => 'collections'],
                    ['key' => 'limit', 'label' => 'تعداد (خالی = همه)', 'type' => 'number'],
                    ['key' => 'size', 'label' => 'اندازهٔ تصویر', 'type' => 'select', 'options' => [
                        'md' => 'متوسط', 'lg' => 'بزرگ', 'xl' => 'خیلی بزرگ',
                    ]],
                    ['key' => 'shape', 'label' => 'نسبت تصویر', 'type' => 'select', 'options' => [
                        'portrait' => 'عمودی (۳:۴)', 'square' => 'مربع', 'landscape' => 'افقی (۴:۳)',
                    ]],
                    ['key' => 'fit', 'label' => 'نوع نمایش تصویر', 'type' => 'select', 'options' => [
                        'cover' => 'پر کردن کادر (عکس)',
                        'contain' => 'نمایش کامل بدون برش (PNG بدون پس‌زمینه)',
                    ]],
                    ['key' => 'show_name', 'label' => 'نمایش نام روی تصویر', 'type' => 'select', 'options' => [
                        'no' => 'فقط تصویر', 'yes' => 'با نام کالکشن',
                    ]],
                    ['key' => 'layout', 'label' => 'چیدمان', 'type' => 'select', 'options' => [
                        'scroll' => 'اسکرول دستی (پیش‌فرض)',
                        'marquee' => 'اسلایدر بی‌نهایت (Marquee)',
                    ]],
                    ['key' => 'speed', 'label' => 'سرعت اسلایدر (فقط در حالت Marquee)', 'type' => 'select', 'options' => [
                        'slow' => 'آرام',
                        'med' => 'متوسط',
                        'fast' => 'سریع',
                    ]],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'gap', 'label' => 'فاصلهٔ بین کارت‌ها', 'type' => 'select', 'options' => [
                        'tight' => 'فشرده', 'normal' => 'معمولی', 'wide' => 'بازتر',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'promo_banner' => [
                'label' => 'بنر تبلیغاتی',
                'icon' => '📣',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'متن', 'type' => 'textarea'],
                    ['key' => 'cta_text', 'label' => 'متن دکمه', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'لینک دکمه', 'type' => 'text'],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                    ['key' => 'style', 'label' => 'پس‌زمینه', 'type' => 'select', 'options' => [
                        'grad-red-light' => 'گرادیان قرمز', 'grad-dark-red' => 'گرادیان تیره‑قرمز', 'grad-dark-light' => 'گرادیان تیره', 'dark' => 'تیره', 'light' => 'روشن',
                    ]],
                ],
            ],
            'rich_text' => [
                'label' => 'متن و عنوان',
                'icon' => '📝',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان', 'type' => 'text'],
                    ['key' => 'body', 'label' => 'متن (HTML مجاز)', 'type' => 'richtext'],
                    ['key' => 'align', 'label' => 'چینش', 'type' => 'select', 'options' => ['right' => 'راست', 'center' => 'وسط']],
                    ['key' => 'width', 'label' => 'پهنای متن', 'type' => 'select', 'options' => [
                        'narrow' => 'باریک (مناسب خواندن)', 'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'html' => [
                'label' => 'کد HTML (نمای کامل)',
                'icon' => '⟨⟩',
                'fields' => [
                    ['key' => 'html', 'label' => 'کد HTML — دقیقاً همان چیزی که می‌نویسید نمایش داده می‌شود', 'type' => 'code'],
                    ['key' => 'width', 'label' => 'پهنای محتوا', 'type' => 'select', 'options' => [
                        'narrow' => 'باریک (مناسب متن)', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                ],
            ],
            'image' => [
                'label' => 'تصویر',
                'icon' => '🏞',
                'fields' => [
                    ['key' => 'image', 'label' => 'تصویر', 'type' => 'image'],
                    ['key' => 'link', 'label' => 'لینک', 'type' => 'text'],
                    ['key' => 'caption', 'label' => 'زیرنویس', 'type' => 'text'],
                    ['key' => 'height', 'label' => 'ارتفاع', 'type' => 'select', 'options' => ['sm' => 'کوتاه', 'md' => 'متوسط', 'lg' => 'بلند']],
                ],
            ],
            'image_duo' => [
                'label' => 'دو تصویر کنار هم',
                'icon' => '🖼🖼',
                'fields' => [
                    ['key' => 'image_a', 'label' => 'تصویر ۱', 'type' => 'image'],
                    ['key' => 'link_a', 'label' => 'لینک ۱', 'type' => 'text'],
                    ['key' => 'image_b', 'label' => 'تصویر ۲', 'type' => 'image'],
                    ['key' => 'link_b', 'label' => 'لینک ۲', 'type' => 'text'],
                    ['key' => 'stack_mobile', 'label' => 'چیدمان موبایل', 'type' => 'select', 'options' => [
                        'stack' => 'عمودی (یکی بالای دیگری)', 'side' => 'افقی (کنار هم — کوچک‌تر)',
                    ]],
                    ['key' => 'aspect', 'label' => 'نسبت تصویر', 'type' => 'select', 'options' => [
                        'portrait' => 'پرتره ۴:۵', 'tall' => 'بلند ۲:۳', 'square' => 'مربع', 'wide' => 'افقی ۴:۳',
                    ]],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'gap', 'label' => 'فاصلهٔ بین', 'type' => 'select', 'options' => [
                        'tight' => 'فشرده', 'normal' => 'معمولی', 'wide' => 'بازتر',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'marquee' => [
                'label' => 'نوار متحرک',
                'icon' => '🔁',
                'fields' => [
                    ['key' => 'items', 'label' => 'عبارت‌ها (هر خط یک مورد)', 'type' => 'lines'],
                    ['key' => 'style', 'label' => 'رنگ', 'type' => 'select', 'options' => ['dark' => 'تیره', 'red' => 'قرمز']],
                ],
            ],
            'features' => [
                'label' => 'ویژگی‌ها / تضمین‌ها',
                'icon' => '✅',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان بخش', 'type' => 'text'],
                    ['key' => 'items', 'label' => 'موارد (هر خط: عنوان :: توضیح)', 'type' => 'lines'],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'concept_stores' => [
                'label' => 'کانسپت‌استورها',
                'icon' => '🏬',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان بخش', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'زیرعنوان بخش', 'type' => 'textarea'],
                    ['key' => 'stores', 'label' => 'کانسپت‌استورها', 'type' => 'repeater', 'sub' => [
                        ['key' => 'name', 'label' => 'نام فروشگاه', 'type' => 'text'],
                        ['key' => 'tagline', 'label' => 'شعار / عنوان کوتاه', 'type' => 'text'],
                        ['key' => 'city', 'label' => 'شهر / موقعیت', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'معرفی کامل', 'type' => 'richtext'],
                        ['key' => 'image', 'label' => 'تصویر اصلی', 'type' => 'image'],
                        ['key' => 'image2', 'label' => 'تصویر دوم (اختیاری)', 'type' => 'image'],
                        ['key' => 'instagram', 'label' => 'اینستاگرام (آیدی یا لینک)', 'type' => 'text'],
                        ['key' => 'website', 'label' => 'وب‌سایت (اختیاری)', 'type' => 'text'],
                    ]],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'newsletter' => [
                'label' => 'عضویت خبرنامه',
                'icon' => '✉️',
                'fields' => [
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                    ['key' => 'heading', 'label' => 'عنوان', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'متن', 'type' => 'textarea'],
                    ['key' => 'channel', 'label' => 'کانال', 'type' => 'select', 'options' => ['telegram' => 'تلگرام', 'sms' => 'پیامک', 'email' => 'ایمیل']],
                ],
            ],
            'pattern_divider' => [
                'label' => 'جداکننده نقش‌مایه',
                'icon' => '➗',
                'fields' => [
                    ['key' => 'style', 'label' => 'رنگ', 'type' => 'select', 'options' => ['light' => 'روشن', 'red' => 'قرمز', 'dark' => 'تیره']],
                    ['key' => 'height', 'label' => 'ارتفاع', 'type' => 'select', 'options' => ['sm' => 'کوتاه', 'md' => 'متوسط']],
                ],
            ],
            'lookbook_grid' => [
                'label' => 'لوک‌بوک (شبکهٔ موزائیکی)',
                'icon' => '🧱',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان بخش', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'زیرعنوان (اختیاری)', 'type' => 'text'],
                    ['key' => 'products', 'label' => 'محصولات انتخابی (جستجو و انتخاب؛ خالی = از منبع زیر استفاده می‌شود)', 'type' => 'multipicker', 'source' => 'products'],
                    ['key' => 'feed', 'label' => 'منبع محصولات (وقتی محصول انتخابی ندارید — دسته یا کالکشن)', 'type' => 'picker', 'source' => 'product_feed'],
                    ['key' => 'limit', 'label' => 'تعداد نمایش (خالی = ۱۲)', 'type' => 'number'],
                    ['key' => 'columns', 'label' => 'تعداد ستون‌ها (دسکتاپ)', 'type' => 'select', 'options' => [
                        '3' => '۳ ستون', '4' => '۴ ستون', '5' => '۵ ستون',
                    ]],
                    ['key' => 'mobile_columns', 'label' => 'تعداد ستون‌ها (موبایل)', 'type' => 'select', 'options' => [
                        '2' => '۲ ستون', '3' => '۳ ستون',
                    ]],
                    ['key' => 'gap', 'label' => 'فاصلهٔ بین کاشی‌ها', 'type' => 'select', 'options' => [
                        'tight' => 'فشرده', 'normal' => 'معمولی', 'wide' => 'بازتر',
                    ]],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'editorial_hero' => [
                'label' => 'هیرو/بنر ادیتوریال (اسلایدر)',
                'icon' => '🎞',
                'fields' => [
                    // Block-level: height + autoplay + global overlay style
                    ['key' => 'height', 'label' => 'ارتفاع', 'type' => 'select', 'options' => [
                        'natural' => 'طبیعی — اندازهٔ خود تصویر (بدون برش)',
                        'strip'   => 'نوار (۲۰vh — بنر باریک)',
                        'short'   => 'کوتاه (۴۰vh — بنر متوسط)',
                        'banner'  => 'بنر (۵۵vh)',
                        'med'     => 'متوسط (۷۵vh)',
                        'tall'    => 'تمام‌صفحه (۱۰۰vh — هیرو کامل، عمودی روی موبایل)',
                        'custom'  => 'سفارشی — با اسلایدر پایین تنظیم کنید',
                    ]],
                    ['key' => 'height_custom', 'label' => 'ارتفاع سفارشی (فقط وقتی حالت «سفارشی» انتخاب شده)', 'type' => 'range', 'min' => 10, 'max' => 120, 'default' => 55, 'unit' => 'vh'],
                    ['key' => 'interval', 'label' => 'فاصله جابه‌جایی اسلاید (ثانیه؛ ۰ = بدون چرخش خودکار)', 'type' => 'number'],
                    ['key' => 'overlay', 'label' => 'لایهٔ تیره روی تصاویر (مشترک همه اسلایدها)', 'type' => 'select', 'options' => [
                        'none' => 'بدون',
                        'soft' => 'ملایم (۲۰٪)',
                        'medium' => 'متوسط (۳۵٪)',
                        'strong' => 'قوی (۵۰٪)',
                    ]],

                    // ----- Full-bleed background layer (block-level) -----
                    // bg_color paints the entire block; bg_image sits over it
                    // at bg_opacity %; bg_blend optionally mixes them.
                    ['key' => 'bg_color', 'label' => 'رنگ پس‌زمینهٔ بلاک (پشت تصویر)', 'type' => 'color'],
                    ['key' => 'bg_image', 'label' => 'تصویر پس‌زمینهٔ بلاک (اختیاری — پشت اسلایدها)', 'type' => 'image'],
                    ['key' => 'bg_opacity', 'label' => 'شفافیت تصویر پس‌زمینه', 'type' => 'range', 'min' => 0, 'max' => 100, 'default' => 100, 'unit' => '٪'],

                    // ----- Floating PNG overlay (block-level) -----
                    // Sits ABOVE the slide image and overlay; positioned by
                    // x/y % per breakpoint so the admin can drop a logo,
                    // sticker or seal anywhere on the banner.
                    ['key' => 'overlay_png', 'label' => 'تصویر روی بنر (PNG شناور — اختیاری)', 'type' => 'image'],
                    ['key' => 'overlay_png_link', 'label' => 'لینک روی PNG (اختیاری — کلیک روی تصویر شناور)', 'type' => 'text'],
                    ['key' => 'overlay_width', 'label' => 'عرض PNG شناور (٪ از عرض بلاک)', 'type' => 'range', 'min' => 1, 'max' => 100, 'default' => 25, 'unit' => '٪'],
                    // Position unit — درصد (٪, scales with the banner) or پیکسل (px,
                    // exact). Drag the PNG on the canvas or type an exact value.
                    ['key' => 'overlay_unit', 'label' => 'واحد موقعیت PNG', 'type' => 'select', 'options' => ['%' => 'درصد (٪)', 'px' => 'پیکسل (px)']],
                    ['key' => 'overlay_x_desktop', 'label' => 'موقعیت افقی PNG (دسکتاپ)', 'type' => 'number'],
                    ['key' => 'overlay_y_desktop', 'label' => 'موقعیت عمودی PNG (دسکتاپ)', 'type' => 'number'],
                    ['key' => 'overlay_x_mobile', 'label' => 'موقعیت افقی PNG (موبایل)', 'type' => 'number'],
                    ['key' => 'overlay_y_mobile', 'label' => 'موقعیت عمودی PNG (موبایل)', 'type' => 'number'],

                    // Per-slide: image (desktop) + image_mobile + all overlay
                    // elements + per-breakpoint position and font size.
                    ['key' => 'slides', 'label' => 'اسلایدها (هر اسلاید عکس، متن و موقعیت مستقل)', 'type' => 'repeater', 'sub' => [
                        ['key' => 'image', 'label' => 'تصویر دسکتاپ (افقی)', 'type' => 'image'],
                        ['key' => 'image_mobile', 'label' => 'تصویر موبایل (عمودی — اختیاری؛ خالی = همان تصویر دسکتاپ)', 'type' => 'image'],
                        ['key' => 'link', 'label' => 'لینک کل تصویر (اختیاری — کلیک روی هر جای اسلاید)', 'type' => 'text'],
                        ['key' => 'kicker', 'label' => 'بالا‌نویس (مثلاً «حالا فعال»)', 'type' => 'text'],
                        ['key' => 'title', 'label' => 'تیتر بزرگ', 'type' => 'text'],
                        ['key' => 'subtitle', 'label' => 'زیر‌نویس کوچک', 'type' => 'text'],
                        ['key' => 'cta_text', 'label' => 'متن دکمه', 'type' => 'text'],
                        ['key' => 'cta_link', 'label' => 'لینک دکمه', 'type' => 'text'],
                        ['key' => 'ends_at', 'label' => 'تایمر شمارش معکوس (تاریخ و ساعت پایان)', 'type' => 'datetime'],
                        ['key' => 'text_color', 'label' => 'رنگ متن', 'type' => 'select', 'options' => [
                            'white' => 'سفید', 'dark' => 'تیره',
                        ]],
                        ['key' => 'title_size', 'label' => 'اندازه تیتر', 'type' => 'select', 'options' => [
                            'sm' => 'کوچک', 'md' => 'متوسط', 'lg' => 'بزرگ', 'xl' => 'عظیم',
                        ]],
                        ['key' => 'position_desktop', 'label' => 'موقعیت متن (دسکتاپ)', 'type' => 'select', 'options' => [
                            'tl' => '↖ بالا چپ', 'tc' => '↑ بالا وسط', 'tr' => '↗ بالا راست',
                            'ml' => '← وسط چپ', 'mc' => '○ وسط',          'mr' => '→ وسط راست',
                            'bl' => '↙ پایین چپ','bc' => '↓ پایین وسط','br' => '↘ پایین راست',
                        ]],
                        ['key' => 'position_mobile', 'label' => 'موقعیت متن (موبایل)', 'type' => 'select', 'options' => [
                            'tl' => '↖ بالا چپ', 'tc' => '↑ بالا وسط', 'tr' => '↗ بالا راست',
                            'ml' => '← وسط چپ', 'mc' => '○ وسط',          'mr' => '→ وسط راست',
                            'bl' => '↙ پایین چپ','bc' => '↓ پایین وسط','br' => '↘ پایین راست',
                        ]],
                        // ---- Font + overlay + CTA styling per slide ----
                        ['key' => 'title_font', 'label' => 'فونت تیتر', 'type' => 'select', 'options' => [
                            'sans'    => 'وزیر — متن فارسی',
                            'display' => 'Jost — لاتین/ادیتوریال',
                            'mono'    => 'JetBrains Mono — تایپ ماشینی',
                        ]],
                        ['key' => 'title_weight', 'label' => 'وزن تیتر', 'type' => 'select', 'options' => [
                            'light' => 'نازک', 'normal' => 'معمولی', 'semibold' => 'نیمه‌پررنگ', 'bold' => 'پررنگ', 'extrabold' => 'فوق‌پررنگ',
                        ]],
                        ['key' => 'title_tracking', 'label' => 'فاصلهٔ حروف تیتر', 'type' => 'select', 'options' => [
                            'tight' => 'فشرده', 'normal' => 'معمولی', 'wide' => 'باز', 'wider' => 'بازتر',
                        ]],
                        ['key' => 'kicker_font', 'label' => 'فونت بالا‌نویس', 'type' => 'select', 'options' => [
                            'sans-italic'    => 'وزیر ایتالیک',
                            'display-italic' => 'Jost ایتالیک',
                            'display-upper'  => 'Jost بزرگ‌نویس',
                        ]],
                        // Per-slide overlay tint + opacity (overrides the block-level overlay for this slide)
                        ['key' => 'slide_overlay_color', 'label' => 'رنگ لایهٔ روی تصویر', 'type' => 'select', 'options' => [
                            'none' => 'بدون', 'dark' => 'تیره', 'light' => 'روشن', 'red' => 'قرمز برند', 'gradient-bottom' => 'گرادیان از پایین',
                        ]],
                        ['key' => 'slide_overlay_opacity', 'label' => 'شفافیت لایه', 'type' => 'select', 'options' => [
                            '0' => '۰٪', '20' => '۲۰٪', '35' => '۳۵٪', '50' => '۵۰٪', '70' => '۷۰٪', '85' => '۸۵٪',
                        ]],
                        // CTA appearance — underlined link or filled pill
                        ['key' => 'cta_style', 'label' => 'سبک دکمه CTA', 'type' => 'select', 'options' => [
                            'underline' => 'لینک با خط‌زیرین (ادیتوریال)',
                            'pill-light' => 'دکمه روشن (پر)',
                            'pill-dark'  => 'دکمه تیره (پر)',
                            'pill-accent'=> 'دکمه قرمز برند',
                            'ghost'      => 'دکمه شبح (بدون پر)',
                        ]],
                        // Padding inside the slide for the overlay text container
                        ['key' => 'text_padding', 'label' => 'فاصله متن از لبهٔ تصویر', 'type' => 'select', 'options' => [
                            'tight' => 'فشرده', 'normal' => 'معمولی', 'loose' => 'بازتر',
                        ]],
                    ]],
                ],
            ],
            'collection_feature' => [
                'label' => 'معرفی کالکشن (ادیتوریال)',
                'icon' => '📰',
                'fields' => [
                    ['key' => 'collection', 'label' => 'کالکشن (جستجو و انتخاب)', 'type' => 'picker', 'source' => 'collections'],
                    ['key' => 'eyebrow', 'label' => 'برچسب کوچک (مثلاً CHIACO COLLECTION)', 'type' => 'text'],
                    ['key' => 'cta_text', 'label' => 'متن دکمه (پیش‌فرض: «مشاهده همه»)', 'type' => 'text'],

                    // Manual product override: if set, these exact products show
                    // in this exact order instead of the collection's auto-feed.
                    ['key' => 'products', 'label' => 'محصولات انتخابی (خالی = جدیدترین‌های همین کالکشن)', 'type' => 'multipicker', 'source' => 'products'],
                    ['key' => 'limit', 'label' => 'تعداد محصول کنار توضیح (۲ تا ۴)', 'type' => 'number'],
                    ['key' => 'show_summary', 'label' => 'نمایش توضیح کوتاه زیر هر محصول', 'type' => 'select', 'options' => [
                        'no' => 'خیر',
                        'yes' => 'بله',
                    ]],

                    // Image: override + size knob. Override beats the collection's image_path.
                    ['key' => 'image_override', 'label' => 'تصویر سفارشی (اختیاری — جایگزین تصویر کالکشن)', 'type' => 'image'],
                    ['key' => 'image_size', 'label' => 'اندازهٔ تصویر', 'type' => 'select', 'options' => [
                        'sm' => 'کوچک (۴:۵)',
                        'md' => 'متوسط (۳:۴)',
                        'lg' => 'بزرگ (۲:۳)',
                        'square' => 'مربع (۱:۱)',
                        'wide'  => 'افقی (۴:۳)',
                    ]],
                    ['key' => 'image_side', 'label' => 'موقعیت تصویر (دسکتاپ)', 'type' => 'select', 'options' => [
                        'start' => 'سمت راست (پیش‌فرض RTL)',
                        'end' => 'سمت چپ',
                    ]],
                    ['key' => 'image_size_mobile', 'label' => 'اندازهٔ تصویر — موبایل', 'type' => 'select', 'options' => [
                        'sm' => 'کوچک ۴:۵', 'md' => 'متوسط ۳:۴', 'lg' => 'بزرگ ۲:۳', 'square' => 'مربع', 'wide' => 'افقی ۴:۳',
                    ]],
                    ['key' => 'tone', 'label' => 'رنگ برشِ مورب', 'type' => 'select', 'options' => [
                        'red' => 'قرمز ایرانی',
                        'dark' => 'تیره چیاکو',
                    ]],
                    ['key' => 'corner', 'label' => 'گوشهٔ برش', 'type' => 'select', 'options' => [
                        'bottom' => 'پایین',
                        'top' => 'بالا',
                    ]],
                    ['key' => 'container', 'label' => 'پهنای ناحیه', 'type' => 'select', 'options' => [
                        'default' => 'پیش‌فرض', 'wide' => 'عریض', 'full' => 'تمام‌عرض',
                    ]],
                    ['key' => 'padding', 'label' => 'فاصله عمودی بخش', 'type' => 'select', 'options' => [
                        'none' => 'بدون', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد',
                    ]],
                ],
            ],
            'velour_banner' => [
                'label' => 'بنر مخملی (پارالاکس)',
                'icon' => '🫧',
                'fields' => [
                    ['key' => 'image', 'label' => 'تصویر بنر', 'type' => 'image'],
                    ['key' => 'image_x', 'label' => 'موقعیت افقی تصویر (۰=چپ تا ۱۰۰=راست)', 'type' => 'range', 'min' => 0, 'max' => 100, 'default' => 50, 'unit' => '٪'],
                    ['key' => 'image_y', 'label' => 'موقعیت عمودی تصویر (۰=بالا تا ۱۰۰=پایین)', 'type' => 'range', 'min' => 0, 'max' => 100, 'default' => 44, 'unit' => '٪'],
                    ['key' => 'image_zoom', 'label' => 'بزرگ‌نمایی تصویر', 'type' => 'range', 'min' => 100, 'max' => 220, 'default' => 132, 'unit' => '٪'],
                    ['key' => 'image_mobile', 'label' => 'تصویر جایگزین برای گوشی (اختیاری)', 'type' => 'image'],
                    ['key' => 'kicker', 'label' => 'بالا‌نویس (مثلاً «کالکشن ویژه»)', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'تیتر (خط اول)', 'type' => 'text'],
                    ['key' => 'title_em', 'label' => 'تیتر ایتالیک (خط دوم — اختیاری)', 'type' => 'text'],
                    ['key' => 'meta', 'label' => 'متن پایین (هر خط جدا)', 'type' => 'lines'],
                    ['key' => 'link', 'label' => 'لینک بنر (اختیاری — کل بنر کلیک‌پذیر می‌شود)', 'type' => 'text'],
                    ['key' => 'accent', 'label' => 'رنگ تأکید (طلایی پیش‌فرض)', 'type' => 'color'],
                    ['key' => 'caption_position', 'label' => 'موقعیت متن', 'type' => 'select', 'options' => [
                        'right' => 'راست', 'left' => 'چپ', 'center' => 'وسط',
                    ]],
                    ['key' => 'height', 'label' => 'ارتفاع', 'type' => 'select', 'options' => [
                        'strip'  => 'نوار (۲۰vh)',
                        'short'  => 'کوتاه (۴۰vh)',
                        'banner' => 'بنر (۵۵vh)',
                        'med'    => 'متوسط (۷۵vh)',
                        'tall'   => 'تمام‌صفحه (۱۰۰vh)',
                        'custom' => 'سفارشی — با اسلایدر پایین',
                    ]],
                    ['key' => 'height_custom', 'label' => 'ارتفاع سفارشی (فقط وقتی «سفارشی» انتخاب شده)', 'type' => 'range', 'min' => 10, 'max' => 120, 'default' => 40, 'unit' => 'vh'],
                    ['key' => 'bubbles', 'label' => 'حباب‌های شناور', 'type' => 'select', 'options' => [
                        'subtle' => 'ملایم', 'rich' => 'پرحجم', 'none' => 'بدون',
                    ]],
                    ['key' => 'parallax', 'label' => 'حرکت پارالاکس با ماوس', 'type' => 'select', 'options' => [
                        'on' => 'روشن', 'off' => 'خاموش',
                    ]],
                ],
            ],
            'countdown_banner' => [
                'label' => 'بنر شمارش معکوس',
                'icon' => '⏳',
                'fields' => [
                    ['key' => 'heading', 'label' => 'عنوان', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'زیرعنوان', 'type' => 'text'],
                    ['key' => 'ends_at', 'label' => 'پایان (YYYY-MM-DD HH:MM، تایم‌زون تهران)', 'type' => 'text'],
                    ['key' => 'cta_text', 'label' => 'متن دکمه', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'لینک دکمه', 'type' => 'text'],
                    ['key' => 'style', 'label' => 'پس‌زمینه', 'type' => 'select', 'options' => [
                        'grad-dark-red' => 'گرادیان تیره‑قرمز', 'grad-red-light' => 'گرادیان قرمز', 'dark' => 'تیره', 'amber' => 'کهربایی',
                    ]],
                ],
            ],
        ];
    }

    public static function types(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    public static function fields(string $type): array
    {
        return self::all()[$type]['fields'] ?? [];
    }

    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? $type;
    }

    /**
     * Inline CSS for a block's wrapper from its universal `_style` layer.
     * Returns 'display:contents' when there are no overrides, so the live
     * layout is byte-identical for blocks that don't use the style controls.
     */
    public static function wrapStyle(array $data): string
    {
        $s = (array) ($data['_style'] ?? []);
        $css = [];
        foreach (['pt' => 'padding-top', 'pb' => 'padding-bottom', 'mt' => 'margin-top', 'mb' => 'margin-bottom'] as $k => $prop) {
            if (($s[$k] ?? '') !== '') {
                $css[] = $prop.':'.(int) $s[$k].'px';
            }
        }
        if (($s['px'] ?? '') !== '') {
            $css[] = 'padding-inline:'.(int) $s['px'].'px';
        }
        if ((int) ($s['maxw'] ?? 0) > 0) {
            $css[] = 'max-width:'.(int) $s['maxw'].'px';
        }
        if (($s['align'] ?? '') === 'center') {
            $css[] = 'margin-inline:auto';
        }
        if ((int) ($s['minh'] ?? 0) > 0) {
            $css[] = 'min-height:'.(int) $s['minh'].'px';
        }
        if ((int) ($s['radius'] ?? 0) > 0) {
            $css[] = 'border-radius:'.(int) $s['radius'].'px';
            $css[] = 'overflow:hidden';
        }
        $bw = (int) ($s['bw'] ?? 0);
        if ($bw > 0) {
            $css[] = 'border:'.$bw.'px solid '.self::cssColor($s['bc'] ?? '#e5e7eb');
        }
        $shadow = [
            'sm' => '0 1px 2px rgba(0,0,0,.08)',
            'md' => '0 6px 18px rgba(0,0,0,.10)',
            'lg' => '0 20px 45px rgba(0,0,0,.16)',
        ][$s['shadow'] ?? ''] ?? '';
        if ($shadow !== '') {
            $css[] = 'box-shadow:'.$shadow;
        }
        if (in_array($s['ta'] ?? '', ['right', 'center', 'left'], true)) {
            $css[] = 'text-align:'.$s['ta'];
        }
        if (($s['bg'] ?? '') !== '') {
            $css[] = 'background-color:'.self::cssColor($s['bg']);
        }

        return $css ? implode(';', $css) : 'display:contents';
    }

    /** Sanitise an admin-supplied colour for inline CSS. */
    private static function cssColor(string $c): string
    {
        return preg_replace('/[^#a-zA-Z0-9(),.%\s]/', '', $c);
    }

    /**
     * Typography declarations from `_style` (colour, weight, line-height,
     * letter-spacing), applied scoped to a block's headings/paragraphs with
     * !important so they override the block's Tailwind utility classes.
     * Empty string when nothing is set.
     */
    public static function typographyStyle(array $data): string
    {
        $s = (array) ($data['_style'] ?? []);
        $css = [];
        if (($s['tc'] ?? '') !== '') {
            $css[] = 'color:'.self::cssColor($s['tc']).' !important';
        }
        if (in_array((string) ($s['fw'] ?? ''), ['300', '400', '500', '600', '700', '800'], true)) {
            $css[] = 'font-weight:'.$s['fw'].' !important';
        }
        if (($s['lh'] ?? '') !== '') {
            $lh = preg_replace('/[^0-9.]/', '', (string) $s['lh']);
            if ($lh !== '') {
                $css[] = 'line-height:'.$lh.' !important';
            }
        }
        if (($s['ls'] ?? '') !== '') {
            $css[] = 'letter-spacing:'.(int) $s['ls'].'px !important';
        }

        return implode(';', $css);
    }

    /**
     * Mobile (<768px) CSS declarations from the `_style.m` overrides — emitted
     * inside a media query keyed on the block's data-bid. Uses !important so it
     * beats the inline desktop styles. Empty string when there are no overrides.
     */
    public static function wrapStyleMobile(array $data): string
    {
        $m = (array) ($data['_style']['m'] ?? []);
        $css = [];
        foreach (['pt' => 'padding-top', 'pb' => 'padding-bottom', 'mt' => 'margin-top', 'mb' => 'margin-bottom'] as $k => $prop) {
            if (($m[$k] ?? '') !== '') {
                $css[] = $prop.':'.(int) $m[$k].'px !important';
            }
        }
        if (($m['px'] ?? '') !== '') {
            $css[] = 'padding-inline:'.(int) $m['px'].'px !important';
        }
        if ((int) ($m['maxw'] ?? 0) > 0) {
            $css[] = 'max-width:'.(int) $m['maxw'].'px !important';
        }
        if ((int) ($m['minh'] ?? 0) > 0) {
            $css[] = 'min-height:'.(int) $m['minh'].'px !important';
        }
        if (in_array($m['ta'] ?? '', ['right', 'center', 'left'], true)) {
            $css[] = 'text-align:'.$m['ta'].' !important';
        }
        if (($m['bg'] ?? '') !== '') {
            $css[] = 'background-color:'.self::cssColor($m['bg']).' !important';
        }

        return implode(';', $css);
    }

    /** Mobile typography declarations (colour/weight/line-height/letter-spacing). */
    public static function typographyStyleMobile(array $data): string
    {
        $m = (array) ($data['_style']['m'] ?? []);
        $css = [];
        if (($m['tc'] ?? '') !== '') {
            $css[] = 'color:'.self::cssColor($m['tc']).' !important';
        }
        if (in_array((string) ($m['fw'] ?? ''), ['300', '400', '500', '600', '700', '800'], true)) {
            $css[] = 'font-weight:'.$m['fw'].' !important';
        }
        if (($m['lh'] ?? '') !== '') {
            $lh = preg_replace('/[^0-9.]/', '', (string) $m['lh']);
            if ($lh !== '') {
                $css[] = 'line-height:'.$lh.' !important';
            }
        }
        if (($m['ls'] ?? '') !== '') {
            $css[] = 'letter-spacing:'.(int) $m['ls'].'px !important';
        }

        return implode(';', $css);
    }

    /**
     * Keep only the keys this block type declares; coerce numeric/lines fields.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public static function sanitize(string $type, array $data): array
    {
        $clean = [];
        foreach (self::fields($type) as $field) {
            $key = $field['key'];
            $val = $data[$key] ?? null;
            if (($field['type'] ?? null) === 'repeater') {
                $clean[$key] = self::sanitizeRepeater($field['sub'] ?? [], $val);
            } else {
                $clean[$key] = self::coerce($field['type'], $val);
                // Purify richtext fields
                if ($field['type'] === 'richtext' && is_string($clean[$key])) {
                    $clean[$key] = self::getPurifier()->purify($clean[$key]);
                }
            }
        }

        // Universal per-block style layer (Elementor-style «پیشرفته» controls) —
        // whitelisted keys, applied as inline CSS on the block wrapper at render.
        if (isset($data['_style']) && is_array($data['_style'])) {
            $st = [];
            foreach (['pt', 'pb', 'px', 'mt', 'mb', 'maxw', 'minh', 'radius', 'bw', 'ls'] as $k) {
                $v = $data['_style'][$k] ?? null;
                if ($v !== null && $v !== '') {
                    $st[$k] = max(0, (int) $v);
                }
            }
            foreach (['align', 'bg', 'bc', 'shadow', 'ta', 'tc', 'fw', 'lh'] as $k) {
                $v = trim((string) ($data['_style'][$k] ?? ''));
                if ($v !== '') {
                    $st[$k] = $v;
                }
            }
            // Per-breakpoint mobile overrides (spacing/size/align) under _style.m.
            if (isset($data['_style']['m']) && is_array($data['_style']['m'])) {
                $m = [];
                foreach (['pt', 'pb', 'px', 'mt', 'mb', 'maxw', 'minh', 'ls'] as $k) {
                    $v = $data['_style']['m'][$k] ?? null;
                    if ($v !== null && $v !== '') {
                        $m[$k] = max(0, (int) $v);
                    }
                }
                foreach (['ta', 'bg', 'tc', 'fw', 'lh'] as $k) {
                    $v = trim((string) ($data['_style']['m'][$k] ?? ''));
                    if ($v !== '') {
                        $m[$k] = $v;
                    }
                }
                if ($m) {
                    $st['m'] = $m;
                }
            }
            if ($st) {
                $clean['_style'] = $st;
            }
        }

        return $clean;
    }

    /** Coerce a single scalar field value by its declared type. */
    private static function coerce(string $type, mixed $val): mixed
    {
        return match ($type) {
            'number' => $val === null || $val === '' ? null : (int) $val,
            'lines' => self::toLines($val),
            'multipicker' => self::toCsv($val),
            default => is_string($val) ? trim($val) : $val,
        };
    }

    /** Normalise a multipicker value (comma string or array) into a clean,
     *  de-duplicated comma-joined slug string. */
    private static function toCsv(mixed $val): string
    {
        $items = is_array($val) ? $val : explode(',', (string) $val);
        $items = array_filter(array_map('trim', $items), fn ($v) => $v !== '');

        return implode(',', array_values(array_unique($items)));
    }

    /**
     * Sanitize a repeater value into a clean list of rows, each limited to the
     * sub-fields the repeater declares.
     *
     * @param  array<int,array>  $sub
     * @return array<int,array<string,mixed>>
     */
    private static function sanitizeRepeater(array $sub, mixed $val): array
    {
        $rows = [];
        foreach ((array) $val as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            $empty = true;
            foreach ($sub as $field) {
                $v = self::coerce($field['type'], $row[$field['key']] ?? null);
                $clean[$field['key']] = $v;
                // Selects always carry a default, so they don't make a row "real".
                if ($field['type'] !== 'select' && $v !== null && $v !== '' && $v !== []) {
                    $empty = false;
                }
            }
            if (! $empty) {
                $rows[] = $clean;
            }
        }

        return $rows;
    }

    /** Normalise a "lines" value (textarea or array) into a clean string array. */
    private static function toLines(mixed $val): array
    {
        if (is_array($val)) {
            $lines = $val;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string) $val) ?: [];
        }

        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }

    /** Get HTMLPurifier instance (lazy singleton). */
    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Doctype', 'XHTML 1.0 Strict');
            // Note: do NOT set the bogus `URI.FilterAssets` directive — it does
            // not exist in HTMLPurifier and throws "Cannot set undefined directive",
            // which 500s every page-save with a rich_text block.
            // You can customize the allowed tags here if needed.
            // For example, to allow only a safe set:
            // $config->set('HTML.Allowed', 'p,br,b,i,strong,em,a[href],ul,ol,li,hr');
            self::$purifier = new HTMLPurifier($config);
        }
        return self::$purifier;
    }
}
