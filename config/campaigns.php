<?php

/*
 | Campaign plan, Aban 1405 → Ordibehesht 1406 (racketclub.ir).
 | Dates are Tehran days (Y-m-d, Gregorian). Shelves, slides, pop-ups, badges
 | and pairs are read live from here; rules, coupons and bundles are written to
 | the DB by `php artisan campaigns:sync` (runs on deploy and daily).
 | Polo jumpers (gampr) are never discounted (config/shop.php).
 */

$card = fn (string $title, string $body, string $cta, string $url, string $code = '') => '<div dir="rtl" style="font-family:Vazirmatn,Tahoma,sans-serif;padding:28px 24px 24px;text-align:center;color:#14213d">'
    .'<p style="margin:0 0 6px;font-size:12px;letter-spacing:.14em;color:#1d5c47">RACKET CLUB</p>'
    .'<h2 style="margin:0 0 10px;font-size:22px;line-height:1.4">'.$title.'</h2>'
    .'<p style="margin:0 0 16px;font-size:14px;line-height:1.9;color:#4a5568">'.$body.'</p>'
    .($code ? '<p style="margin:0 0 16px"><span style="display:inline-block;padding:8px 16px;border:1.5px dashed #1d5c47;border-radius:10px;font:700 18px monospace;letter-spacing:.08em">'.$code.'</span></p>' : '')
    .'<a href="'.$url.'" target="_top" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#14213d;color:#fff;text-decoration:none;font-size:14px">'.$cta.'</a></div>';

$tees = ['tyshrt'];
$tacky = 'aadd-aorgryp-rakt-klab-super-tacky-1480';
$dry = 'aadd-aorgryp-rakt-klab-super-dry-1482';

return [

    'shelves' => [
        'kits' => ['name' => 'کیت‌ها و بسته‌ها', 'description' => 'کیت شروع پدل، کیف بازیکن و بسته‌های اورگریپ راکت کلاب؛ ارزان‌تر از خرید تکی.',
            'bundles' => true, 'in_stock' => true],
        'last-size' => ['name' => 'آخرین سایز', 'description' => 'آخرین تک‌سایزهای طرح‌های قدیمی راکت کلاب.',
            'max_stock' => 3, 'exclude_categories' => ['gampr']],
        'court-winter' => ['name' => 'زمین زمستانی', 'description' => 'پولو جامپر راکت کلاب با فلیس دورو برای بازی در هوای سرد.',
            'categories' => ['gampr'], 'in_stock' => true],
        'tees' => ['name' => 'تی‌شرت‌های راکت کلاب', 'description' => 'تی‌شرت‌های نخ پنبه راکت کلاب برای پدل و تنیس؛ سفارش تیمی از ۱۰ عدد با تخفیف.',
            'categories' => $tees, 'in_stock' => true],
        'gifts-under-3m' => ['name' => 'هدیه زیر ۳ میلیون', 'description' => 'هدیه برای پدل‌بازها و تنیس‌بازها زیر سه میلیون تومان: کلاه، جوراب، اورگریپ.',
            'max_price' => 3_000_000, 'in_stock' => true, 'exclude_categories' => ['gampr']],
        'outdoor' => ['name' => 'بازی در فضای باز', 'description' => 'حوله میکروفایبر، توت‌بگ، کلاه و پولوشرت ورزشی راکت کلاب برای بهار.',
            'categories' => ['holh', 'mtfrkh', 'klah'], 'in_stock' => true, 'from' => '2027-03-17'],
    ],

    'pairs' => [
        'tyshrt' => ['klah', 'loazm-ganby', 'gampr'], 'gampr' => ['tyshrt', 'klah'], 'klah' => ['tyshrt', 'loazm-ganby'],
        'loazm-ganby' => ['klah', 'holh', 'mtfrkh'], 'holh' => ['mtfrkh', 'loazm-ganby'], 'mtfrkh' => ['holh', 'klah'],
    ],

    'rules' => [
        'second-order' => ['name' => 'سفارش دوم', 'label' => '۱۰٪ تخفیف سفارش دوم شما',
            'conditions' => [['user_previous_order_count', 'min' => 1, 'max' => 1]], 'actions' => [['cart_discount_percent', 10]]],
        'team-10' => ['name' => 'سفارش تیمی · ۱۰ تی‌شرت', 'label' => 'سفارش تیمی: ۱۵٪ تخفیف تی‌شرت‌ها (۱۰ عدد و بیشتر)',
            'conditions' => [['item_count_from_categories', 'categories' => $tees, 'min' => 10, 'max' => 19]], 'actions' => [['item_discount_percent', 15]]],
        'team-20' => ['name' => 'سفارش تیمی · ۲۰ تی‌شرت', 'label' => 'سفارش تیمی: ۲۰٪ تخفیف تی‌شرت‌ها (۲۰ عدد و بیشتر)',
            'conditions' => [['item_count_from_categories', 'categories' => $tees, 'min' => 20]], 'actions' => [['item_discount_percent', 20]]],

        'bf-ship' => ['name' => 'بلک فرایدی · ارسال رایگان', 'label' => 'ارسال رایگان بلک فرایدی (از ۵ میلیون)', 'from' => '2026-11-24', 'to' => '2026-11-30',
            'conditions' => [['cart_subtotal', 'min' => 5_000_000]], 'actions' => [['free_shipping', 0]]],
        'bf-9' => ['name' => 'بلک فرایدی · از ۹ میلیون', 'label' => '۸٪ تخفیف بلک فرایدی (از ۹ میلیون)', 'from' => '2026-11-24', 'to' => '2026-11-30',
            'conditions' => [['cart_subtotal', 'min' => 9_000_000, 'max' => 13_999_999]], 'actions' => [['cart_discount_percent', 8]]],
        'bf-14' => ['name' => 'بلک فرایدی · از ۱۴ میلیون', 'label' => '۱۲٪ تخفیف بلک فرایدی (از ۱۴ میلیون)', 'from' => '2026-11-24', 'to' => '2026-11-30',
            'conditions' => [['cart_subtotal', 'min' => 14_000_000, 'max' => 19_999_999]], 'actions' => [['cart_discount_percent', 12]]],
        'bf-20' => ['name' => 'بلک فرایدی · از ۲۰ میلیون', 'label' => '۱۵٪ تخفیف بلک فرایدی (از ۲۰ میلیون)', 'from' => '2026-11-24', 'to' => '2026-11-30',
            'conditions' => [['cart_subtotal', 'min' => 20_000_000]], 'actions' => [['cart_discount_percent', 15]]],

        'last-size' => ['name' => 'آخرین سایز', 'label' => '۲۵٪ تخفیف آخرین سایزها', 'from' => '2027-01-02', 'to' => '2027-01-22',
            'conditions' => [['item_count_from_shelf', 'shelf' => 'last-size', 'min' => 1]], 'actions' => [['item_discount_percent', 25]]],

        'partner-set' => ['name' => 'یار دوبل', 'label' => 'یار دوبل: ۱۰٪ تخفیف دو تی‌شرت', 'from' => '2027-01-30', 'to' => '2027-02-19',
            'conditions' => [['item_count_from_categories', 'categories' => $tees, 'min' => 2, 'max' => 9]], 'actions' => [['item_discount_percent', 10]]],

        'nowruz-3' => ['name' => 'نوروز · ۳ قطعه', 'label' => '۸٪ تخفیف نوروزی (۳ قطعه)', 'from' => '2027-02-27', 'to' => '2027-03-16',
            'conditions' => [['item_count_from_categories', 'categories' => ['tyshrt', 'klah', 'holh', 'mtfrkh'], 'min' => 3, 'max' => 4]], 'actions' => [['cart_discount_percent', 8]]],
        'nowruz-5' => ['name' => 'نوروز · ۵ قطعه', 'label' => '۱۲٪ تخفیف نوروزی (۵ قطعه)', 'from' => '2027-02-27', 'to' => '2027-03-16',
            'conditions' => [['item_count_from_categories', 'categories' => ['tyshrt', 'klah', 'holh', 'mtfrkh'], 'min' => 5, 'max' => 7]], 'actions' => [['cart_discount_percent', 12]]],
        'nowruz-8' => ['name' => 'نوروز · ۸ قطعه و بیشتر', 'label' => '۱۵٪ تخفیف نوروزی (۸ قطعه و بیشتر)', 'from' => '2027-02-27', 'to' => '2027-03-16',
            'conditions' => [['item_count_from_categories', 'categories' => ['tyshrt', 'klah', 'holh', 'mtfrkh'], 'min' => 8]], 'actions' => [['cart_discount_percent', 15]]],

        'spring-set' => ['name' => 'ست بهاره · پولوشرت و کلاه', 'label' => 'ست بهاره: ۵۰۰ هزار تومان تخفیف پولوشرت ورزشی و کلاه', 'from' => '2027-04-03', 'to' => '2027-04-30',
            'conditions' => [['item_products', 'products' => ['poloshrt-orzshy-rakt-klab-1476']], ['item_products', 'products' => ['klah-rakt-klab-9']]],
            'actions' => [['cart_discount_fixed', 500000]]],
    ],

    'coupons' => [
        'RCFIRST' => ['type' => 'percent', 'value' => 7, 'min_subtotal' => 3_000_000, 'first_order_only' => true, 'per_user_limit' => 1,
            'description' => 'خرید اول: ۷٪ از ۳ میلیون تومان'],
        'EARLYRC' => ['type' => 'percent', 'value' => 12, 'per_user_limit' => 1, 'description' => 'دسترسی زودهنگام بلک فرایدی (تلگرام)',
            'starts_at' => '2026-11-20 20:30:00', 'expires_at' => '2026-11-23 20:29:59'],
    ],

    // Free-size bundle products: slug => [name, off % below the parts' total, items: [[product slug, qty, 'color' => …]]].
    // Each part's variant is the named colour, else the one with most stock.
    'bundles' => [
        'kit-overgrip-tacky-3' => ['name' => 'بسته ۳ عددی اورگریپ Super Tacky', 'off' => 9,
            'summary' => 'سه اورگریپ Super Tacky راکت کلاب؛ چسبندگی بالا برای دست خشک.', 'items' => [[$tacky, 3]]],
        'kit-overgrip-dry-3' => ['name' => 'بسته ۳ عددی اورگریپ Super Dry', 'off' => 9,
            'summary' => 'سه اورگریپ Super Dry راکت کلاب؛ جذب عرق برای دست‌های عرق‌کرده.', 'items' => [[$dry, 3]]],
        'kit-overgrip-tacky-6' => ['name' => 'بسته ۶ عددی اورگریپ Super Tacky', 'off' => 14,
            'summary' => 'شش اورگریپ Super Tacky راکت کلاب؛ برای یک فصل بازی.', 'items' => [[$tacky, 6]]],
        'kit-overgrip-dry-6' => ['name' => 'بسته ۶ عددی اورگریپ Super Dry', 'off' => 14,
            'summary' => 'شش اورگریپ Super Dry راکت کلاب؛ برای یک فصل بازی.', 'items' => [[$dry, 6]]],
        'kit-padel-starter' => ['name' => 'کیت شروع پدل', 'off' => 11,
            'summary' => 'کلاه راکت کلاب، جوراب ورزشی و سه اورگریپ Super Tacky؛ هدیه‌ای برای کسی که تازه پدل را شروع کرده.',
            'items' => [['klah-rakt-klab-9', 1], ['gorab-rakt-klab-122', 1, 'color' => 'مشکی'], [$tacky, 3]]],
        'kit-player-bag' => ['name' => 'کیف بازیکن', 'off' => 11,
            'summary' => 'توت‌بگ راکت کلاب، حوله میکروفایبر ۸۰×۱۵۰ و جوراب ورزشی؛ همه‌چیز برای رفتن به زمین.',
            'items' => [['tot-bg-rakt-klab-121', 1], ['holh-mykro-faybr-rakt-klab-764', 1], ['gorab-rakt-klab-122', 1, 'color' => 'مشکی']]],
    ],

    // Mix-and-match sets (discount rules above), shown as cards on /bundles and
    // under the cart when a matching item is in it.
    'sets' => [
        'team' => ['name' => 'سفارش تیمی', 'text' => '۱۰ تی‌شرت ۱۵٪ و ۲۰ تی‌شرت ۲۰٪ تخفیف؛ برای باشگاه و آکادمی', 'categories' => $tees, 'url' => '/shop?shelf=tees'],
        'partner' => ['name' => 'یار دوبل', 'text' => 'دو تی‌شرت راکت کلاب، ۱۰٪ تخفیف', 'categories' => $tees, 'url' => '/shop?shelf=tees',
            'from' => '2027-01-30', 'to' => '2027-02-19'],
        'spring' => ['name' => 'ست بهاره', 'text' => 'پولوشرت ورزشی + کلاه، ۵۰۰ هزار تومان تخفیف', 'products' => ['poloshrt-orzshy-rakt-klab-1476', 'klah-rakt-klab-9'],
            'url' => '/product/poloshrt-orzshy-rakt-klab-1476', 'from' => '2027-04-03', 'to' => '2027-04-30'],
    ],

    'badges' => [
        ['categories' => $tees, 'text' => 'سفارش تیمی: ۱۰ تی‌شرت ۱۵٪، ۲۰ تی‌شرت ۲۰٪ تخفیف'],
        ['categories' => ['loazm-ganby'], 'text' => 'بسته‌های ۳ و ۶ عددی اورگریپ ارزان‌ترند؛ در «کیت‌ها و بسته‌ها»'],
        ['from' => '2026-11-24', 'to' => '2026-11-30', 'exclude_categories' => ['gampr'], 'text' => 'بلک فرایدی: از ۹ میلیون ۸٪، از ۱۴ میلیون ۱۲٪، از ۲۰ میلیون ۱۵٪ تخفیف'],
        ['from' => '2026-11-24', 'to' => '2026-11-30', 'categories' => ['gampr'], 'text' => 'بلک فرایدی: پولو جامپر تخفیف ندارد، اما در مبلغ پله‌ها حساب می‌شود'],
        ['from' => '2027-01-02', 'to' => '2027-01-22', 'shelf' => 'last-size', 'text' => 'آخرین سایز: ۲۵٪ تخفیف در سبد خرید'],
        ['from' => '2027-01-30', 'to' => '2027-02-19', 'categories' => $tees, 'text' => 'یار دوبل: دو تی‌شرت بخرید، ۱۰٪ تخفیف بگیرید'],
        ['from' => '2027-02-27', 'to' => '2027-03-16', 'exclude_categories' => ['gampr', 'loazm-ganby'], 'text' => 'نوروز: ۳ قطعه ۸٪، ۵ قطعه ۱۲٪، ۸ قطعه و بیشتر ۱۵٪ تخفیف'],
        ['from' => '2027-04-03', 'to' => '2027-04-30', 'products' => ['poloshrt-orzshy-rakt-klab-1476', 'klah-rakt-klab-9'], 'text' => 'ست بهاره: پولوشرت ورزشی + کلاه = ۵۰۰ هزار تومان تخفیف'],
    ],

    'slides' => [
        ['from' => '2026-11-07', 'to' => '2026-11-20', 'text' => 'زمین زمستانی: پولو جامپر راکت کلاب رسید', 'url' => '/shop?shelf=court-winter'],
        ['from' => '2026-11-14', 'to' => '2026-11-20', 'text' => 'بلک فرایدی ۳ آذر شروع می‌شود؛ دسترسی زودهنگام در تلگرام', 'url' => '/shop'],
        ['from' => '2026-11-24', 'to' => '2026-11-30', 'text' => 'بلک فرایدی: از ۹ میلیون ۸٪ · از ۱۴ میلیون ۱۲٪ · از ۲۰ میلیون ۱۵٪ · ارسال رایگان از ۵ میلیون', 'url' => '/shop'],
        ['from' => '2026-12-05', 'to' => '2026-12-17', 'text' => 'هدیه یلدا برای پدل‌بازها: کیت شروع پدل و کیف بازیکن', 'url' => '/shop?shelf=kits'],
        ['from' => '2026-12-14', 'to' => '2026-12-17', 'text' => 'برای رسیدن قبل از یلدا تا پنجشنبه ۲۶ آذر سفارش دهید', 'url' => '/shop?shelf=kits'],
        ['from' => '2026-12-19', 'to' => '2026-12-23', 'text' => 'هدیه روز پدر: کیف بازیکن راکت کلاب', 'url' => '/product/kit-player-bag'],
        ['from' => '2027-01-02', 'to' => '2027-01-22', 'text' => 'آخرین سایزها با ۲۵٪ تخفیف', 'url' => '/shop?shelf=last-size'],
        ['from' => '2027-01-09', 'to' => '2027-01-29', 'text' => 'ماه اورگریپ: بسته‌های ۳ و ۶ عددی ارزان‌تر از خرید تکی', 'url' => '/shop?shelf=kits'],
        ['from' => '2027-01-30', 'to' => '2027-02-19', 'text' => 'یار دوبل: دو تی‌شرت، ۱۰٪ تخفیف', 'url' => '/shop?shelf=tees'],
        ['from' => '2027-02-27', 'to' => '2027-03-16', 'text' => 'نوروز: ۳ قطعه ۸٪ · ۵ قطعه ۱۲٪ · ۸ قطعه ۱۵٪', 'url' => '/shop'],
        ['from' => '2027-03-08', 'to' => '2027-03-12', 'text' => 'آخرین روز ارسال پستی قبل از عید: ۲۱ اسفند', 'url' => '/shop'],
        ['from' => '2027-03-17', 'to' => '2027-03-25', 'text' => 'سفارش‌های این روزها از ۵ فروردین ارسال می‌شوند', 'url' => '/shop?shelf=outdoor'],
        ['from' => '2027-03-27', 'to' => '2027-04-02', 'text' => 'سیزده‌بدر: حوله، توت‌بگ و کلاه برای بازی در فضای باز', 'url' => '/shop?shelf=outdoor'],
        ['from' => '2027-04-03', 'to' => '2027-04-30', 'text' => 'ست بهاره: پولوشرت ورزشی + کلاه، ۵۰۰ هزار تومان تخفیف', 'url' => '/product/poloshrt-orzshy-rakt-klab-1476'],
        ['text' => 'سفارش تیمی برای باشگاه و آکادمی: ۱۰ تی‌شرت ۱۵٪، ۲۰ تی‌شرت ۲۰٪', 'url' => '/shop?shelf=tees'],
    ],

    'popups' => [
        'welcome' => ['html' => $card('به راکت کلاب خوش آمدید', '۷٪ تخفیف برای اولین سفارش از ۳ میلیون تومان. کد را در سبد خرید وارد کنید.', 'دیدن کیت‌ها', '/shop?shelf=kits', 'RCFIRST'),
            'trigger' => 'delay', 'delay' => 12, 'frequency' => 'once', 'pages' => 'all'],
        'bf' => ['html' => $card('بلک فرایدی راکت کلاب', 'از ۹ میلیون ۸٪، از ۱۴ میلیون ۱۲٪ و از ۲۰ میلیون ۱۵٪ تخفیف، با ارسال رایگان از ۵ میلیون. تا ۹ آذر.', 'ساختن کیت کامل', '/shop'),
            'from' => '2026-11-24', 'to' => '2026-11-30', 'delay' => 5, 'pages' => 'home'],
        'yalda' => ['html' => $card('هدیه یلدا برای پدل‌بازها', 'کیت شروع پدل و کیف بازیکن، ارزان‌تر از خرید تکی. برای رسیدن قبل از یلدا تا ۲۶ آذر سفارش دهید.', 'دیدن کیت‌ها', '/shop?shelf=kits'),
            'from' => '2026-12-05', 'to' => '2026-12-17', 'delay' => 6, 'pages' => 'home'],
        'partner' => ['html' => $card('یار دوبل', 'پدل دونفره است. دو تی‌شرت راکت کلاب برای شما و یار دوبلتان، با ۱۰٪ تخفیف.', 'دیدن تی‌شرت‌ها', '/shop?shelf=tees'),
            'from' => '2027-01-30', 'to' => '2027-02-17', 'delay' => 6, 'pages' => 'home'],
        'nowruz' => ['html' => $card('پلکان نوروزی', '۳ قطعه ۸٪، ۵ قطعه ۱۲٪ و ۸ قطعه و بیشتر ۱۵٪ تخفیف. آخرین روز ارسال پستی: ۲۱ اسفند.', 'خرید عید', '/shop'),
            'from' => '2027-02-27', 'to' => '2027-03-16', 'delay' => 5, 'pages' => 'home'],
    ],
];
