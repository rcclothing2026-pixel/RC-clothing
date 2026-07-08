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
                'label' => 'Editorial Homepage (default)',
                'description' => 'Full-screen hero → product carousel → hero → carousel → category tiles → guarantees',
                'blocks' => self::defaultHomeBlocks(),
            ],
            'collection-drop' => [
                'label' => 'New Collection Launch Page',
                'description' => 'One editorial hero + collection feature + lookbook + guarantees',
                'blocks' => [
                    ['type' => 'editorial_hero', 'data' => ['height' => 'tall', 'overlay' => 'soft', 'slides' => [[
                        'image' => '', 'kicker' => 'Now Live', 'title' => 'The New Collection',
                        'cta_text' => 'Shop', 'cta_link' => '/shop',
                        'text_color' => 'white', 'title_size' => 'xl',
                        'position_desktop' => 'bc', 'position_mobile' => 'bc',
                    ]]]],
                    ['type' => 'collection_feature', 'data' => ['image_size' => 'lg', 'tone' => 'red', 'corner' => 'bottom', 'limit' => 4]],
                    ['type' => 'lookbook_grid', 'data' => ['heading' => 'The Lookbook', 'columns' => '4', 'mobile_columns' => '2']],
                    ['type' => 'features', 'data' => []],
                ],
            ],
            'sale' => [
                'label' => 'Sale Page',
                'description' => 'Countdown banner + product carousel + promo banner + category tiles',
                'blocks' => [
                    ['type' => 'editorial_hero', 'data' => ['height' => 'banner', 'overlay' => 'medium', 'slides' => [[
                        'image' => '', 'kicker' => 'For a Limited Time', 'title' => 'Season Sale',
                        'cta_text' => 'View Products', 'cta_link' => '/shop',
                        'text_color' => 'white', 'title_size' => 'xl', 'cta_style' => 'pill-accent',
                        'position_desktop' => 'mc', 'position_mobile' => 'mc',
                    ]]]],
                    ['type' => 'product_grid', 'data' => ['heading' => '24 Hours Only', 'layout' => 'carousel', 'card_size' => 'compact', 'card_style' => 'minimal']],
                    ['type' => 'promo_banner', 'data' => ['heading' => 'Complimentary Shipping', 'text' => 'On orders over 1,000,000 Toman', 'cta_text' => 'Shop Now', 'cta_link' => '/shop', 'style' => 'grad-dark-red']],
                    ['type' => 'category_tiles', 'data' => ['layout' => 'marquee', 'speed' => 'med']],
                ],
            ],
            'about' => [
                'label' => 'About Us (editorial)',
                'description' => 'Hero + manifesto text + features + collection carousel',
                'blocks' => [
                    ['type' => 'editorial_hero', 'data' => ['height' => 'banner', 'overlay' => 'soft', 'slides' => [[
                        'image' => '', 'kicker' => 'Who We Are', 'title' => 'RACKET CLUB',
                        'text_color' => 'white', 'title_size' => 'xl',
                        'position_desktop' => 'mc', 'position_mobile' => 'mc',
                    ]]]],
                    ['type' => 'rich_text', 'data' => ['heading' => 'The Racket Club', 'body' => '<p>Write the story of the house here — the manifesto, the inspiration and the values behind The Art of Leisure.</p>', 'align' => 'center', 'width' => 'narrow', 'padding' => 'lg']],
                    ['type' => 'features', 'data' => ['items' => ['Considered Craft :: Selected fabric with a clean, quiet finish', 'Timeless Design :: Balanced between the classic and the contemporary', 'Considered Shipping :: Carefully dispatched, wherever you are']]],
                    ['type' => 'collection_scroller', 'data' => ['heading' => 'The Collections', 'size' => 'lg', 'shape' => 'portrait']],
                ],
            ],
            'blank' => [
                'label' => 'Blank (start from scratch)',
                'description' => 'No blocks — build it yourself from the block tray.',
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
            // Hero — the trio on navy (rc-solo-terracotta is actually the navy
            // group shot; filenames are scrambled by design). Image is an
            // admin-editable field, so it can be swapped in the page builder.
            ['type' => 'editorial_hero', 'data' => [
                'image' => '/img/photography/rc-solo-terracotta.jpg',
                'kicker' => 'Racket Club · Est. 2025',
                'title' => 'THE ART OF LEISURE',
                'cta_text' => 'Explore the Collection',
                'cta_link' => '/shop',
                'height' => 'tall',
                'text_color' => 'white',
                'overlay' => 'soft',
            ]],
            ['type' => 'product_grid', 'data' => [
                'heading' => 'The Essentials',
                'feed' => 'featured',
                'limit' => 8,
                'layout' => 'carousel',
                'card_size' => 'compact',
                'card_style' => 'minimal',
            ]],
            // The Art of Leisure — manifesto (sell the moment, not the product).
            ['type' => 'rich_text', 'data' => [
                'heading' => 'The Life Off the Court',
                'body' => '<p>Racket Club is an ode to the moments between the matches — the slow mornings, the long lunches, the easy evenings. Quiet luxury for those who have nothing to prove. Legends are made on the court; a legacy is lived off it.</p>',
                'align' => 'center',
                'width' => 'narrow',
                'padding' => 'lg',
            ]],
            // Editorial lookbook duo — both images are admin-editable fields.
            ['type' => 'image_duo', 'data' => [
                'image_a' => '/img/photography/rc-couple-terracotta.jpg',
                'link_a' => '/shop',
                'image_b' => '/img/photography/rc-padel-back.jpg',
                'link_b' => '/shop',
                'aspect' => 'portrait',
                'stack_mobile' => 'stack',
                'container' => 'wide',
                'gap' => 'normal',
                'padding' => 'md',
            ]],
            // Brand band — the towel shot carries the "Legends & Legacy" mark.
            ['type' => 'editorial_hero', 'data' => [
                'image' => '/img/photography/rc-laughing-terracotta.jpg',
                'kicker' => 'Off the Court',
                'title' => 'MADE FOR THE MOMENT',
                'cta_text' => 'Shop New Arrivals',
                'cta_link' => '/shop?sort=new',
                'height' => 'med',
                'text_color' => 'white',
                'overlay' => 'soft',
            ]],
            ['type' => 'product_grid', 'data' => [
                'heading' => 'New Arrivals',
                'feed' => 'new',
                'limit' => 8,
                'layout' => 'carousel',
                'card_size' => 'compact',
                'card_style' => 'minimal',
            ]],
            ['type' => 'category_tiles', 'data' => [
                'heading' => 'Shop by Category',
                'limit' => 12,
                'layout' => 'marquee',
                'speed' => 'med',
            ]],
            ['type' => 'features', 'data' => ['heading' => '', 'items' => [
                'Considered Shipping :: Carefully packed and dispatched, wherever you are',
                'Secure Payment :: Trusted online checkout through a verified gateway',
                'Quality, Guaranteed :: Considered fabric and finish, with easy returns',
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
                'title' => 'About',
                'seo_description' => 'About Racket Club — the art of leisure',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">ABOUT RACKET CLUB</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <p>Racket Club is a quiet-luxury leisurewear house built around a single idea: The Art of Leisure. We make considered pieces for the moments between the matches — the slow mornings, the long lunches, the easy evenings. Nothing loud, nothing to prove; just fabric, cut and finish, done well.</p>
    <p>Legends are made on the court. A legacy is lived off it. Every piece is chosen for how it feels to wear over the years, not just the season — timeless design, balanced between the classic and the contemporary, made to be lived in.</p>
    <p>For any question, or to follow up on an order, we are here on the <a href="/contact" class="text-accent-600 hover:underline">Contact</a> page.</p>
</div>
HTML,
            ],
            [
                'slug' => 'faq',
                'title' => 'FAQ',
                'seo_description' => 'Answers to common questions about shopping with Racket Club',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">FREQUENTLY ASKED QUESTIONS</h1>
<div class="mt-8 space-y-4">
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">How do I place an order?</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">Add the piece you want to your bag, choose your size and colour, then enter your address and shipping method at checkout.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">How is payment handled?</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">Payment is made securely online through a trusted, verified gateway.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">How long will my order take to arrive?</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">Depending on the shipping method you choose, typically between 1 and 4 business days.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">What if the size isn't right?</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">Unworn pieces can be returned or exchanged within 7 days. Full details are on the Shipping &amp; Returns page.</p>
    </details>
    <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
        <summary class="cursor-pointer text-sm font-semibold text-brand-800">Is the stock shown on the site accurate?</summary>
        <p class="mt-3 text-sm leading-7 text-brand-600">Yes. Stock is synced live with our inventory; sizes that are out of stock are shown as unavailable.</p>
    </details>
</div>
HTML,
            ],
            [
                'slug' => 'shipping-returns',
                'title' => 'Shipping & Returns',
                'seo_description' => 'Shipping, returns and exchange terms at Racket Club',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">SHIPPING &amp; RETURNS</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <h2 class="pt-2 font-bold text-brand-900">Shipping</h2>
    <p>Orders are prepared and dispatched once payment is confirmed. The shipping method and cost are chosen at checkout, and orders above a set threshold ship complimentary.</p>
    <h2 class="pt-2 font-bold text-brand-900">Returns and Exchanges</h2>
    <p>Within 7 days of delivery, unworn pieces in their original condition with tags intact may be returned or exchanged. To begin, let us know via the <a href="/contact" class="text-accent-600 hover:underline">Contact</a> page.</p>
    <h2 class="pt-2 font-bold text-brand-900">Return Conditions</h2>
    <ul class="list-inside list-disc space-y-1">
        <li>The piece must be unworn, with its original packaging and tags.</li>
        <li>Return shipping is covered by us when the fault is ours.</li>
        <li>Refunds are issued within 72 business hours of the piece being inspected.</li>
    </ul>
</div>
HTML,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms',
                'seo_description' => 'Terms and conditions for shopping with Racket Club',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">TERMS &amp; CONDITIONS</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <p>By placing an order with Racket Club, you accept the terms below. They exist for clarity and to protect the interests of both sides.</p>
    <h2 class="pt-2 font-bold text-brand-900">1. Placing an Order</h2>
    <p>Prices are shown in Toman and include applicable tax. Once payment succeeds, your order is confirmed and sent for processing.</p>
    <h2 class="pt-2 font-bold text-brand-900">2. Price and Availability</h2>
    <p>Prices and availability may change; what applies is the information shown at the moment your order is placed. If an item sells out after payment, the amount is refunded.</p>
    <h2 class="pt-2 font-bold text-brand-900">3. Shipping</h2>
    <p>Shipping time and cost are calculated based on the method you choose. Full details are on the Shipping &amp; Returns page.</p>
    <h2 class="pt-2 font-bold text-brand-900">4. Privacy</h2>
    <p>Your information is protected in line with our <a href="/page/privacy" class="text-accent-600 hover:underline">Privacy</a> policy.</p>
</div>
HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy',
                'seo_description' => 'Privacy policy and how Racket Club protects customer data',
                'html' => <<<'HTML'
<h1 class="text-2xl font-bold text-brand-900">PRIVACY</h1>
<div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
    <p>Protecting your information matters to us. This page explains what data we collect and why.</p>
    <h2 class="pt-2 font-bold text-brand-900">What We Collect</h2>
    <p>Your name, phone number and address, to process and ship your order; and payment details, which are handled only by the payment gateway and are never stored on our servers.</p>
    <h2 class="pt-2 font-bold text-brand-900">How We Use It</h2>
    <p>Only to fulfil your order, provide support and, if you choose, share news of new pieces. Your information is never sold to third parties.</p>
    <h2 class="pt-2 font-bold text-brand-900">Security</h2>
    <p>The site runs over an encrypted connection (HTTPS), and access to data is limited and controlled.</p>
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
