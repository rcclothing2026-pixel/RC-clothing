<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A storefront page composed of an ordered list of typed content blocks
 * (see App\Support\Blocks\BlockRegistry). The homepage is the page flagged
 * is_home; additional pages are served at /page/{slug}.
 */
class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'blocks', 'is_published', 'is_home',
        'show_in_nav', 'seo_title', 'seo_description', 'position',
        'bg_color', 'bg_image',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
            'is_home' => 'boolean',
            'show_in_nav' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('id');
    }

    /** The published homepage, if one has been built. */
    public static function home(): ?self
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pages')) {
            return null;
        }

        return static::query()->published()->where('is_home', true)->first();
    }

    /**
     * The homepage, always. If none exists yet it is provisioned from the
     * default block layout, so the storefront home is never hard-coded and is
     * always editable in the page builder. Returns null if the table isn't
     * there yet (fresh install, no migrate) so callers can fall back.
     */
    public static function provisionHome(): ?self
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pages')) {
            return null;
        }
        if ($home = static::query()->where('is_home', true)->first()) {
            return $home;
        }

        return static::create([
            'title' => 'صفحه اصلی',
            'slug' => 'home',
            'is_home' => true,
            'is_published' => true,
            'blocks' => static::defaultHomeBlocks(),
        ]);
    }

    /**
     * Pre-composed page templates — admin can apply any of these to a page
     * in one click. Each entry returns a blocks array ready to be saved.
     *
     * @return array<string, array{label: string, description: string, blocks: array}>
     */
    public static function templates(): array
    {
        return [
            'editorial-home' => [
                'label' => 'صفحهٔ اصلی ادیتوریال (پیش‌فرض)',
                'description' => 'هیرو تمام‌صفحه → کاروسل محصول → هیرو → کاروسل → کاشی دسته‌بندی‌ها → تضمین‌ها',
                'blocks' => self::defaultHomeBlocks(),
            ],
            'collection-drop' => [
                'label' => 'صفحهٔ معرفی کالکشن جدید',
                'description' => 'یک هیرو ادیتوریال + معرفی کالکشن + لوک‌بوک + تضمین‌ها',
                'blocks' => [
                    ['type' => 'editorial_hero', 'data' => ['height' => 'tall', 'overlay' => 'soft', 'slides' => [[
                        'image' => '', 'kicker' => 'حالا فعال', 'title' => 'کالکشن جدید',
                        'cta_text' => 'خرید', 'cta_link' => '/shop',
                        'text_color' => 'white', 'title_size' => 'xl',
                        'position_desktop' => 'bc', 'position_mobile' => 'bc',
                    ]]]],
                    ['type' => 'collection_feature', 'data' => ['image_size' => 'lg', 'tone' => 'red', 'corner' => 'bottom', 'limit' => 4]],
                    ['type' => 'lookbook_grid', 'data' => ['heading' => 'لوک‌بوک', 'columns' => '4', 'mobile_columns' => '2']],
                    ['type' => 'features', 'data' => []],
                ],
            ],
            'sale' => [
                'label' => 'صفحهٔ تخفیف / حراج',
                'description' => 'بنر شمارش معکوس + کاروسل محصول + بنر تبلیغاتی + کاشی دسته‌بندی',
                'blocks' => [
                    ['type' => 'editorial_hero', 'data' => ['height' => 'banner', 'overlay' => 'medium', 'slides' => [[
                        'image' => '', 'kicker' => 'برای زمان محدود', 'title' => 'تخفیف ویژه',
                        'cta_text' => 'مشاهده محصولات', 'cta_link' => '/shop',
                        'text_color' => 'white', 'title_size' => 'xl', 'cta_style' => 'pill-accent',
                        'position_desktop' => 'mc', 'position_mobile' => 'mc',
                    ]]]],
                    ['type' => 'product_grid', 'data' => ['heading' => 'فقط ۲۴ ساعت', 'layout' => 'carousel', 'card_size' => 'compact', 'card_style' => 'minimal']],
                    ['type' => 'promo_banner', 'data' => ['heading' => 'ارسال رایگان', 'text' => 'برای خریدهای بالای ۱ میلیون تومان', 'cta_text' => 'خرید کنید', 'cta_link' => '/shop', 'style' => 'grad-dark-red']],
                    ['type' => 'category_tiles', 'data' => ['layout' => 'marquee', 'speed' => 'med']],
                ],
            ],
            'about' => [
                'label' => 'دربارهٔ ما (ادیتوریال)',
                'description' => 'هیرو + متن مانیفست + ویژگی‌ها + کاروسل کالکشن',
                'blocks' => [
                    ['type' => 'editorial_hero', 'data' => ['height' => 'banner', 'overlay' => 'soft', 'slides' => [[
                        'image' => '', 'kicker' => 'ما کی هستیم', 'title' => 'CHIACO',
                        'text_color' => 'white', 'title_size' => 'xl',
                        'position_desktop' => 'mc', 'position_mobile' => 'mc',
                    ]]]],
                    ['type' => 'rich_text', 'data' => ['heading' => 'برند چیاکو', 'body' => '<p>متن دربارهٔ برند را اینجا بنویسید — مانیفست، الهام و ارزش‌ها.</p>', 'align' => 'right', 'width' => 'narrow', 'padding' => 'lg']],
                    ['type' => 'features', 'data' => ['items' => ['دوخت ایرانی :: پارچهٔ منتخب با دوختی تمیز', 'طراحی امروزی :: متعادل بین سنت و حال', 'ارسال سریع :: به سراسر کشور']]],
                    ['type' => 'collection_scroller', 'data' => ['heading' => 'کالکشن‌ها', 'size' => 'lg', 'shape' => 'portrait']],
                ],
            ],
            'blank' => [
                'label' => 'خالی (شروع از صفر)',
                'description' => 'بدون بلاک — خودتان از تری بلاک‌ها بسازید.',
                'blocks' => [],
            ],
        ];
    }

    /**
     * Default homepage composition — rastah-style editorial rhythm:
     * editorial hero → minimal-card carousel → editorial hero → carousel
     * → category marquee → trust badges. Used for fresh installs and by
     * Page::provisionHome(). Hero images ship empty so admins are
     * prompted to upload their own (block renders nothing until then) —
     * no stock placeholder ships.
     */
    public static function defaultHomeBlocks(): array
    {
        return [
            ['type' => 'editorial_hero', 'data' => [
                'image' => '',
                'kicker' => 'به‌زودی',
                'title' => 'کالکشن نو',
                'cta_text' => 'مشاهده',
                'cta_link' => '/shop',
                'height' => 'tall',
                'text_color' => 'white',
                'overlay' => 'soft',
            ]],
            ['type' => 'product_grid', 'data' => [
                'heading' => 'منتخب چیاکو',
                'feed' => 'featured',
                'limit' => 8,
                'layout' => 'carousel',
                'card_size' => 'compact',
                'card_style' => 'minimal',
            ]],
            ['type' => 'editorial_hero', 'data' => [
                'image' => '',
                'kicker' => 'حالا فعال',
                'title' => 'جدیدترین‌ها',
                'cta_text' => 'خرید کنید',
                'cta_link' => '/shop?sort=new',
                'height' => 'med',
                'text_color' => 'white',
                'overlay' => 'soft',
            ]],
            ['type' => 'product_grid', 'data' => [
                'heading' => 'جدیدترین‌ها',
                'feed' => 'new',
                'limit' => 8,
                'layout' => 'carousel',
                'card_size' => 'compact',
                'card_style' => 'minimal',
            ]],
            ['type' => 'category_tiles', 'data' => [
                'heading' => 'دسته‌بندی‌ها',
                'limit' => 12,
                'layout' => 'marquee',
                'speed' => 'med',
            ]],
            ['type' => 'features', 'data' => ['heading' => '', 'items' => [
                'ارسال سریع :: بسته‌بندی و ارسال در کوتاه‌ترین زمان به سراسر کشور',
                'پرداخت امن :: پرداخت آنلاین مطمئن از طریق درگاه معتبر',
                'ضمانت کیفیت :: تضمین کیفیت پارچه و دوخت با امکان بازگشت کالا',
            ]]],
        ];
    }

    /**
     * The built-in content / legal pages (about, FAQ, terms, privacy, shipping &
     * returns), each provisioned as a single raw-HTML block so the whole page is
     * editable in the page builder. Seeded idempotently (create-if-missing) by a
     * migration + the seeder; once created the admin owns the content. Mirrors
     * what resources/views/pages/<slug>.blade.php rendered before, so nothing
     * visibly changes until the admin edits it.
     *
     * @return array<int,array{slug:string,title:string,seo_description:string,html:string}>
     */
    public static function contentPageSpecs(): array
    {
        return [
            [
                'slug' => 'about',
                'title' => 'درباره چیاکو',
                'seo_description' => 'درباره برند پوشاک چیاکو',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">درباره چیاکو</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <p>چیاکو یک برند پوشاک ایرانی است که با تمرکز بر طراحی امروزی، پارچه‌ی باکیفیت و دوختِ تمیز، لباس‌هایی برای پوشش روزمره عرضه می‌کند. هدف ما ساده است: لباسی که راحت بپوشی، خوب بمانَد و به‌قیمت منصفانه به دستت برسد.</p>
    <p>همه‌ی محصولات با دقت انتخاب و کنترل کیفیت می‌شوند و موجودی فروشگاه به‌صورت زنده با انبار ما هماهنگ است؛ یعنی چیزی که می‌بینی واقعاً موجود است.</p>
    <p>برای هر پرسش یا پیگیری سفارش، از صفحه‌ی <a href="/contact" class="text-accent-600 hover:underline">تماس با ما</a> در کنار شما هستیم.</p>
</div>
HTML,
            ],
            [
                'slug' => 'faq',
                'title' => 'سوالات متداول',
                'seo_description' => 'پاسخ پرسش‌های پرتکرار درباره خرید از چیاکو',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">سوالات متداول</h1>
<div class="mt-8 space-y-4">
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">چطور سفارش ثبت کنم؟</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">محصول مورد نظر را به سبد خرید اضافه کنید، سایز و رنگ را انتخاب کنید و در مرحله‌ی پرداخت آدرس و روش ارسال را مشخص کنید.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">پرداخت چگونه انجام می‌شود؟</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">پرداخت به‌صورت آنلاین و امن از طریق درگاه‌های معتبر بانکی انجام می‌شود.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">سفارش چند روزه به دستم می‌رسد؟</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">بسته به روش ارسال انتخابی، معمولاً بین ۱ تا ۴ روز کاری.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">اگر سایز مناسب نبود چه کنم؟</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">تا ۷ روز امکان بازگشت یا تعویض کالای استفاده‌نشده وجود دارد. جزئیات در صفحه‌ی شرایط ارسال و بازگشت.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">موجودی سایت دقیق است؟</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">بله، موجودی به‌صورت زنده با انبار هماهنگ می‌شود؛ سایزهای ناموجود غیرفعال نمایش داده می‌شوند.</p>
    </details>
</div>
HTML,
            ],
            [
                'slug' => 'shipping-returns',
                'title' => 'شرایط ارسال و بازگشت کالا',
                'seo_description' => 'شرایط ارسال، بازگشت و تعویض کالا در چیاکو',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">شرایط ارسال و بازگشت کالا</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <h2 class="pt-2 font-bold text-brand-900">ارسال</h2>
    <p>سفارش‌ها پس از تأیید پرداخت آماده و ارسال می‌شوند. روش و هزینه‌ی ارسال در مرحله‌ی پرداخت قابل انتخاب است و برای سفارش‌های بالای سقف مشخص، ارسال رایگان خواهد بود.</p>
    <h2 class="pt-2 font-bold text-brand-900">بازگشت و تعویض</h2>
    <p>تا ۷ روز پس از دریافت، در صورت استفاده‌نشدن و سالم بودن کالا و برچسب‌ها، امکان بازگشت یا تعویض وجود دارد. برای شروع فرایند، از صفحه‌ی <a href="/contact" class="text-accent-600 hover:underline">تماس با ما</a> اطلاع دهید.</p>
    <h2 class="pt-2 font-bold text-brand-900">شرایط بازگشت</h2>
    <ul class="list-inside list-disc space-y-1">
        <li>کالا باید استفاده‌نشده و با بسته‌بندی و برچسب اصلی باشد.</li>
        <li>هزینه‌ی بازگشت در صورت ایراد از سمت ما به‌عهده‌ی فروشگاه است.</li>
        <li>مبلغ پس از بررسی کالا حداکثر ظرف ۷۲ ساعت کاری بازگردانده می‌شود.</li>
    </ul>
</div>
HTML,
            ],
            [
                'slug' => 'terms',
                'title' => 'قوانین و مقررات',
                'seo_description' => 'قوانین و مقررات خرید از فروشگاه چیاکو',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">قوانین و مقررات</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <p>با ثبت سفارش در چیاکو، شما قوانین زیر را می‌پذیرید. این قوانین برای شفافیت و حفظ حقوق طرفین تنظیم شده است.</p>
    <h2 class="pt-2 font-bold text-brand-900">۱. ثبت سفارش</h2>
    <p>قیمت‌ها به تومان و شامل ارزش افزوده هستند. پس از پرداخت موفق، سفارش ثبت و برای پردازش ارسال می‌شود.</p>
    <h2 class="pt-2 font-bold text-brand-900">۲. قیمت و موجودی</h2>
    <p>قیمت‌ها و موجودی ممکن است تغییر کنند؛ ملاک، اطلاعات لحظه‌ی ثبت سفارش است. در صورت اتمام موجودی پس از پرداخت، مبلغ بازگردانده می‌شود.</p>
    <h2 class="pt-2 font-bold text-brand-900">۳. ارسال</h2>
    <p>زمان و هزینه‌ی ارسال بر اساس روش انتخابی شما محاسبه می‌شود. جزئیات در صفحه‌ی شرایط ارسال و بازگشت آمده است.</p>
    <h2 class="pt-2 font-bold text-brand-900">۴. حریم خصوصی</h2>
    <p>اطلاعات شما طبق سیاست <a href="/page/privacy" class="text-accent-600 hover:underline">حریم خصوصی</a> محافظت می‌شود.</p>
</div>
HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => 'حریم خصوصی',
                'seo_description' => 'سیاست حریم خصوصی و حفاظت از داده‌های مشتریان چیاکو',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">حریم خصوصی</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <p>حفظ اطلاعات شما برای ما مهم است. این صفحه توضیح می‌دهد چه داده‌هایی و چرا جمع‌آوری می‌شوند.</p>
    <h2 class="pt-2 font-bold text-brand-900">داده‌هایی که جمع می‌کنیم</h2>
    <p>نام، شماره تماس و آدرس برای پردازش و ارسال سفارش؛ و اطلاعات پرداخت که فقط نزد درگاه بانکی پردازش می‌شود و روی سرور ما ذخیره نمی‌شود.</p>
    <h2 class="pt-2 font-bold text-brand-900">استفاده از داده‌ها</h2>
    <p>صرفاً برای انجام سفارش، پشتیبانی و در صورت تمایل شما، اطلاع‌رسانی محصولات. اطلاعات شما به اشخاص ثالث فروخته نمی‌شود.</p>
    <h2 class="pt-2 font-bold text-brand-900">امنیت</h2>
    <p>ارتباط سایت رمزنگاری‌شده (HTTPS) است و دسترسی به داده‌ها محدود و کنترل‌شده می‌باشد.</p>
</div>
HTML,
            ],
        ];
    }

    /**
     * Default block layout for the gift / bundle landing page (/page/gift).
     * Editable in the page builder once provisioned — the admin swaps the
     * collection_scroller's `ref` for the actual gift collections and the
     * product_grid feed for hand-picked bundle products.
     */
    public static function defaultGiftBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'badge' => 'برای شما، برای آنها',
                'title' => 'پک‌های هدیه چیاکو',
                'accent' => 'هدیه',
                'subtitle' => 'پک‌های دست‌چین برای روزهای ویژه — یک خرید، چند سورپرایز.',
                'cta_text' => 'دیدن همهٔ پک‌ها',
                'cta_link' => '/shop',
                'style' => 'grad-red-light',
            ]],
            ['type' => 'collection_scroller', 'data' => [
                'heading' => 'پک‌های دست‌چین',
                'size' => 'lg',
                'shape' => 'portrait',
                'fit' => 'cover',
                'show_name' => 'yes',
            ]],
            ['type' => 'product_grid', 'data' => [
                'heading' => 'پر فروش‌ترین هدیه‌ها',
                'feed' => 'featured',
                'limit' => 8,
            ]],
            ['type' => 'rich_text', 'data' => [
                'heading' => 'هدیه‌ای که هرگز فراموش نمی‌شود',
                'body' => '<p>هر پک با دقت ترکیب شده تا تجربهٔ کامل و به‌یادماندنی به دست گیرنده برسد. بسته‌بندی شیک، ارسال سریع، و امکان ارسال مستقیم به آدرس گیرنده.</p>',
                'align' => 'center',
            ]],
            ['type' => 'features', 'data' => ['heading' => '', 'items' => [
                'بسته‌بندی هدیه :: جعبهٔ شیک و کارت پیام، آماده برای تقدیم',
                'ارسال به آدرس گیرنده :: ارسال مستقیم با نام شما به آدرس مخاطب',
                'بازگشت آسان :: تا ۷ روز ضمانت بازگشت کالا، حتی برای پک‌ها',
            ]]],
        ];
    }

    /**
     * Provision the editable gift landing page if it doesn't already exist —
     * create-if-missing, never clobbers admin edits. Mirrors provisionContentPages.
     */
    public static function provisionGiftPage(): void
    {
        static::firstOrCreate(['slug' => 'gift'], [
            'title' => 'هدیه‌ها',
            'is_home' => false,
            'is_published' => true,
            'show_in_nav' => true,
            'seo_title' => 'پک‌های هدیه چیاکو',
            'seo_description' => 'پک‌های دست‌چین شده برای هدیه دادن — بسته‌بندی شیک و ارسال به سراسر کشور.',
            'blocks' => static::defaultGiftBlocks(),
        ]);
    }

    /**
     * Provision the built-in content pages as editable page-builder rows, without
     * ever clobbering an admin's edits: only pages that don't already exist (by
     * slug) are created. Shared by the seeder and the provisioning migration.
     */
    public static function provisionContentPages(): void
    {
        foreach (static::contentPageSpecs() as $spec) {
            static::firstOrCreate(['slug' => $spec['slug']], [
                'title' => $spec['title'],
                'is_home' => false,
                'is_published' => true,
                'show_in_nav' => false,
                'seo_description' => $spec['seo_description'],
                'blocks' => [['type' => 'html', 'data' => ['html' => $spec['html'], 'width' => 'narrow']]],
            ]);
        }
    }

    /** Normalised blocks list: always an array of ['type'=>..., 'data'=>[...], '_v'=>...]. */
    public function blockList(): array
    {
        return collect($this->blocks ?? [])
            ->filter(fn ($b) => is_array($b) && ! empty($b['type']))
            ->map(fn ($b, $i) => [
                'type' => $b['type'],
                'data' => (array) ($b['data'] ?? []),
                '_v'   => (string) ($b['_v'] ?? 'all'),
                // Stable per-block id — links a rendered block on the canvas to
                // its editor card. Backfilled deterministically for legacy blocks
                // (persisted on next save via parseBlocks()).
                '_bid' => (string) ($b['_bid'] ?? ('b'.substr(md5($b['type'].json_encode($b['data'] ?? []).$i), 0, 10))),
            ])
            ->values()
            ->all();
    }

    /* ---------- Auto brick pages for catalog (category/collection) ---------- */

    /** Deterministic slug for a category/collection's auto-built brick page. */
    public static function brickSlug(string $type, string $slug): string
    {
        return ($type === 'collection' ? 'col-' : 'cat-').$slug;
    }

    /**
     * Find-or-create the brick/masonry page for a category or collection. A
     * single lookbook_grid block fed by the catalog feed; fully editable
     * afterwards. Idempotent — an existing page is reused untouched, so admin
     * edits are never clobbered. $type is 'category' or 'collection'.
     */
    public static function brickForCatalog(string $type, string $slug, string $name): self
    {
        return static::firstOrCreate(
            ['slug' => static::brickSlug($type, $slug)],
            [
                'title' => $name,
                'is_published' => true,
                'is_home' => false,
                'blocks' => [
                    ['type' => 'lookbook_grid', 'data' => [
                        'heading' => $name,
                        'subtitle' => '',
                        'feed' => $type.':'.$slug,
                        'columns' => 4,
                        'mobile_columns' => 2,
                        'gap' => 'normal',
                        'padding' => 'md',
                        'limit' => 30,
                    ]],
                ],
            ]
        );
    }

    /**
     * Tear down the brick page and any menu items pointing at a deleted
     * category/collection (the auto-built /page/... link and the direct
     * /shop?filter=slug link). Called from the Category/Collection delete hook.
     */
    public static function removeBrick(string $type, string $slug): void
    {
        $pageSlug = static::brickSlug($type, $slug);
        static::where('slug', $pageSlug)->delete();

        $filter = $type === 'collection' ? 'collection' : 'category';
        MenuItem::where('url', '/page/'.$pageSlug)
            ->orWhere('url', 'like', '%'.$filter.'='.$slug)
            ->orWhere('url', 'like', '%'.$filter.'='.$slug.'&%')
            ->delete();
    }
}
