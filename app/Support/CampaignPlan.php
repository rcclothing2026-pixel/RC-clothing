<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Collection as ShopCollection;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The editable campaign plan. config/campaigns.php is the default; once the
 * owner edits the plan in NJ Focus, the edited copy (campaign_plans, one row)
 * replaces it for the whole app — config('campaigns') — so shelves, badges,
 * slides, pop-ups, sets and the rules/coupons/bundles that campaigns:sync
 * writes all follow it. «Back to the default plan» deletes the row.
 *
 * The editor speaks a flat JSON shape (lists with a key field, conditions as
 * {type, min, …}); toEditor()/fromEditor() convert to and from the config shape.
 */
class CampaignPlan
{
    /** Keyed sections (key => item) and list sections, in editor order. */
    public const KEYED = ['campaigns', 'rules', 'sales', 'coupons', 'bundles', 'sets', 'popups', 'shelves'];

    public const LISTS = ['slides', 'badges'];

    /** The sections StoqS owns while it manages this site's promotions (read-only here then). */
    public const STOQS_SECTIONS = ['rules', 'sales', 'coupons'];

    public const STOQS_LOCKED = 'تخفیف‌ها و کدهای این سایت در StoqS مدیریت می‌شوند؛ آن‌ها را در StoqS («برنامه‌ریزی کمپین») تغییر دهید.';

    /** Whether StoqS runs this site's discounts (the rules, product sales and codes are locked). */
    public static function stoqsManaged(): bool
    {
        return \App\Services\StockKeeping\StoqsPromotions::managed();
    }

    /** Percent discounts are capped at shop.max_discount_percent; surcharges at 100. */
    private const PERCENT = ['cart_discount_percent', 'item_discount_percent'];

    private const SURCHARGE_PERCENT = ['item_surcharge_percent', 'cart_surcharge_percent'];

    /** Condition params that are lists and must not be empty. */
    private const LIST_PARAMS = ['products', 'categories', 'collections', 'brands', 'group_ids', 'phones', 'cities', 'method_keys', 'method_ids'];

    /** Every condition and action the discount engine knows. */
    public static function conditions(): array
    {
        return array_keys(DiscountRule::conditionOptions());
    }

    public static function actions(): array
    {
        return array_keys(DiscountRule::actionOptions());
    }

    /** Swap the edited plan into config. Called first thing in AppServiceProvider::boot. */
    public static function apply(): void
    {
        if (config('campaigns_file') === null) {
            config(['campaigns_file' => config('campaigns', [])]);
        }
        try {
            $json = DB::table('campaign_plans')->where('id', 1)->value('plan');
        } catch (\Throwable) {
            return;   // table not migrated yet
        }
        $plan = $json ? json_decode($json, true) : null;
        config(['campaigns' => is_array($plan) ? array_replace(config('campaigns_file'), $plan) : config('campaigns_file')]);
    }

    public static function edited(): bool
    {
        try {
            return DB::table('campaign_plans')->where('id', 1)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Validate and store an edited plan (editor shape), then sync rules,
     * coupons and bundles. Returns the list of problems; empty on success.
     */
    public static function save(array $editor, ?string $by = null): array
    {
        $plan = self::fromEditor($editor);
        if (self::stoqsManaged()) {
            // StoqS's discounts count: the plan's own rules, product sales and codes stay as
            // they were (any change to them is refused); the content is saved as usual.
            $old = config('campaigns', []);
            foreach (self::STOQS_SECTIONS as $sec) {
                if (json_encode(array_values($plan[$sec] ?? [])) !== json_encode(array_values(self::fromEditor(self::toEditor($old))[$sec] ?? []))
                    || array_keys($plan[$sec] ?? []) !== array_keys($old[$sec] ?? [])) {
                    return [self::STOQS_LOCKED];
                }
                $plan[$sec] = $old[$sec] ?? [];
            }
        }
        if ($errors = self::validate($plan)) {
            return $errors;
        }
        $old = config('campaigns', []);

        DB::transaction(function () use ($plan, $old, $by) {
            DB::table('campaign_plans')->updateOrInsert(['id' => 1], [
                'plan' => json_encode($plan, JSON_UNESCAPED_UNICODE),
                'updated_by' => $by ? mb_substr($by, 0, 120) : null,
                'updated_at' => now(), 'created_at' => now(),
            ]);
            self::retire($old, $plan);
        });
        config(['campaigns' => array_replace(config('campaigns_file'), $plan)]);
        Artisan::call('campaigns:sync');

        return [];
    }

    /** Back to config/campaigns.php. Refused while StoqS manages the discounts (returns the problems). */
    public static function reset(): array
    {
        if (self::stoqsManaged()) {
            return [self::STOQS_LOCKED];
        }
        $old = config('campaigns', []);
        DB::table('campaign_plans')->where('id', 1)->delete();
        config(['campaigns' => config('campaigns_file')]);
        self::retire($old, config('campaigns'));
        Artisan::call('campaigns:sync');

        return [];
    }

    /** Coupons and bundle products dropped from the plan are switched off, never deleted. */
    private static function retire(array $old, array $new): void
    {
        $gone = array_diff(array_keys($old['coupons'] ?? []), array_keys($new['coupons'] ?? []));
        if ($gone) {
            Coupon::whereIn('code', $gone)->update(['is_active' => false]);
        }
        $gone = array_diff(array_keys($old['bundles'] ?? []), array_keys($new['bundles'] ?? []));
        if ($gone) {
            Product::whereIn('slug', $gone)->where('is_bundle', true)->update(['is_active' => false]);
        }
    }

    // ── editor shape ────────────────────────────────────────────────────────

    public static function toEditor(array $plan): array
    {
        $out = [];
        foreach (self::KEYED as $s) {
            $out[$s] = [];
            foreach ($plan[$s] ?? [] as $key => $item) {
                $item = match ($s) {
                    'rules' => self::ruleToEditor($item),
                    'sales' => ProductSales::toEditor($item),
                    'bundles' => array_merge($item, ['items' => array_map(fn ($i) => ['product' => $i[0], 'qty' => $i[1] ?? 1, 'color' => $i['color'] ?? ''], $item['items'] ?? [])]),
                    default => $item,
                };
                $out[$s][] = ['key' => (string) $key] + $item;
            }
        }
        foreach (self::LISTS as $s) {
            $out[$s] = array_values($plan[$s] ?? []);
        }
        $out['pairs'] = collect($plan['pairs'] ?? [])->map(fn ($to, $from) => ['category' => $from, 'with' => array_values($to)])->values()->all();

        return $out;
    }

    public static function ruleToEditor(array $r): array
    {
        $r['conditions'] = array_map(fn ($c) => ['type' => $c[0] ?? ''] + Arr::except($c, [0]), $r['conditions'] ?? []);
        $r['actions'] = array_map(fn ($a) => ['type' => $a[0] ?? '', 'value' => $a[1] ?? 0] + Arr::except($a, [0, 1]), $r['actions'] ?? []);

        return $r;
    }

    public static function fromEditor(array $e): array
    {
        $plan = [];
        foreach (self::KEYED as $s) {
            $plan[$s] = [];
            foreach ((array) ($e[$s] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = trim((string) ($item['key'] ?? ''));
                unset($item['key']);
                $item = self::clean($item);
                if ($s === 'rules') {
                    $item = self::ruleFromEditor($item);
                }
                if ($s === 'sales') {
                    $item = ProductSales::fromEditor($item);
                }
                if ($s === 'bundles') {
                    $item['items'] = array_values(array_map(fn ($i) => array_filter([(string) ($i['product'] ?? ''), max(1, (int) ($i['qty'] ?? 1)), 'color' => (string) ($i['color'] ?? '')], fn ($v) => $v !== ''), (array) ($item['items'] ?? [])));
                }
                $plan[$s][$key] = $item;
            }
        }
        foreach (self::LISTS as $s) {
            $plan[$s] = array_values(array_filter(array_map(fn ($i) => is_array($i) ? self::clean($i) : null, (array) ($e[$s] ?? []))));
        }
        $plan['pairs'] = [];
        foreach ((array) ($e['pairs'] ?? []) as $p) {
            if (! empty($p['category'])) {
                $plan['pairs'][(string) $p['category']] = array_values(array_filter((array) ($p['with'] ?? [])));
            }
        }

        return $plan;
    }

    /** A rule from the editor ({type, …} rows) to config shape ([type, …] rows). */
    public static function ruleFromEditor(array $item): array
    {
        $item = self::clean($item);
        $item['conditions'] = array_values(array_map(function ($c) {
            $c = (array) $c;
            $out = [(string) ($c['type'] ?? '')] + self::clean(Arr::except($c, ['type']));
            foreach (['method_ids'] as $k) {
                if (isset($out[$k])) {
                    $out[$k] = array_map('intval', (array) $out[$k]);
                }
            }

            return $out;
        }, (array) ($item['conditions'] ?? [])));
        $item['actions'] = array_values(array_map(fn ($a) => [(string) ($a['type'] ?? ''), (int) ($a['value'] ?? 0)] + self::clean(Arr::except((array) $a, ['type', 'value'])), (array) ($item['actions'] ?? [])));

        return $item;
    }

    /** Drop empty strings, nulls and empty lists; numeric strings become numbers. */
    private static function clean(array $item): array
    {
        $out = [];
        foreach ($item as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (is_string($v) && in_array($k, ['min', 'max', 'value', 'off', 'delay', 'max_stock', 'max_price', 'min_subtotal', 'max_subtotal', 'max_discount', 'usage_limit', 'per_user_limit', 'priority', 'max_uses', 'max_uses_per_user'], true) && is_numeric($v)) {
                $v = (int) $v;
            }
            if (is_array($v) && array_is_list($v)) {
                $v = array_values(array_filter($v, fn ($x) => $x !== null && $x !== ''));
                if ($v === []) {
                    continue;
                }
            }
            $out[$k] = $v;
        }

        return $out;
    }

    // ── validation ──────────────────────────────────────────────────────────

    public static function validate(array $plan): array
    {
        $max = (int) config('shop.max_discount_percent', 25);
        $err = [];
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v);
        $slug = fn ($k) => (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', (string) $k);

        $campaigns = $plan['campaigns'] ?? [];
        foreach ($campaigns as $k => $c) {
            $n = $c['name'] ?? $k;
            if (! $slug($k)) {
                $err[] = "کمپین «{$n}»: شناسه فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.";
            }
            if (empty($c['name'])) {
                $err[] = "کمپین «{$k}»: نام ندارد.";
            }
            if (! $date($c['from'] ?? null) || ! $date($c['to'] ?? null) || $c['to'] < $c['from']) {
                $err[] = "کمپین «{$n}»: تاریخ شروع و پایان درست نیست.";
            }
        }

        $label = ['rules' => 'تخفیف', 'sales' => 'تخفیف محصولی', 'coupons' => 'کد', 'bundles' => 'بسته', 'sets' => 'ست', 'popups' => 'پاپ‌آپ', 'shelves' => 'صفحه', 'slides' => 'نوار بالا', 'badges' => 'برچسب'];
        foreach ($label as $s => $what) {
            foreach ($plan[$s] ?? [] as $k => $i) {
                $name = $what.' «'.($i['label'] ?? $i['name'] ?? $i['title'] ?? $i['text'] ?? $k).'»';
                if (! empty($i['campaign']) && ! isset($campaigns[$i['campaign']])) {
                    $err[] = "$name: کمپین «{$i['campaign']}» وجود ندارد.";
                }
                foreach (['from', 'to'] as $d) {
                    if (isset($i[$d]) && ! $date($i[$d])) {
                        $err[] = "$name: تاریخ «{$i[$d]}» درست نیست.";
                    }
                }
                if (isset($i['from'], $i['to']) && $i['to'] < $i['from']) {
                    $err[] = "$name: پایان قبل از شروع است.";
                }
                if (in_array($s, ['rules', 'sales', 'bundles', 'sets', 'popups', 'shelves'], true) && ! $slug($k)) {
                    $err[] = "$name: شناسه فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.";
                }
            }
        }

        foreach ($plan['rules'] ?? [] as $k => $r) {
            $err = array_merge($err, self::checkRule($r, (string) $k, array_keys($plan['shelves'] ?? [])));
        }
        foreach ($plan['sales'] ?? [] as $k => $s) {
            $err = array_merge($err, ProductSales::check($s, (string) $k));
            if (isset($plan['rules'][$k.'--p1']) || preg_grep('/^'.preg_quote((string) $k, '/').'--[pn]\\d+$/', array_map('strval', array_keys($plan['rules'] ?? [])))) {
                $err[] = "تخفیف محصولی «{$k}»: شناسه با یک تخفیف دیگر یکی است.";
            }
        }

        foreach ($plan['coupons'] ?? [] as $code => $c) {
            if (! preg_match('/^[A-Z0-9_-]{3,30}$/', (string) $code)) {
                $err[] = "کد «{$code}»: فقط حروف بزرگ انگلیسی و عدد، ۳ تا ۳۰ حرف.";
            }
            $type = $c['type'] ?? 'percent';
            if (! in_array($type, ['percent', 'fixed', 'free_shipping'], true)) {
                $err[] = "کد «{$code}»: نوع «{$type}» شناخته نیست.";
            } elseif ($type === 'percent' && (($c['value'] ?? 0) < 1 || $c['value'] > $max)) {
                $err[] = "کد «{$code}»: درصد باید بین ۱ و {$max} باشد.";
            } elseif ($type === 'fixed' && ($c['value'] ?? 0) < 1) {
                $err[] = "کد «{$code}»: مبلغ تخفیف خالی است.";
            }
            foreach (['starts_at', 'expires_at'] as $d) {
                if (isset($c[$d]) && ! strtotime((string) $c[$d])) {
                    $err[] = "کد «{$code}»: زمان «{$c[$d]}» درست نیست.";
                }
            }
        }

        foreach ($plan['bundles'] ?? [] as $k => $b) {
            if (empty($b['name']) || array_sum(array_map(fn ($i) => (int) ($i[1] ?? 1), $b['items'] ?? [])) < 2) {
                $err[] = "بسته «{$k}»: نام و دست‌کم دو کالا لازم است.";
            }
            if (($b['off'] ?? 0) < 0 || ($b['off'] ?? 0) > $max) {
                $err[] = "بسته «{$k}»: تخفیف باید بین ۰ و {$max} درصد باشد.";
            }
        }
        foreach ($plan['popups'] ?? [] as $k => $p) {
            if (empty($p['html']) && empty($p['title'])) {
                $err[] = "پاپ‌آپ «{$k}»: عنوان ندارد.";
            }
        }
        foreach (['slides', 'badges'] as $s) {
            foreach ($plan[$s] ?? [] as $i) {
                if (empty($i['text'])) {
                    $err[] = $label[$s].': یک ردیف متن ندارد.';
                }
            }
        }
        if (! isset($plan['shelves']['outlet'])) {
            $err[] = 'صفحهٔ «outlet» (اوتلت) لازم است؛ آن را حذف نکنید.';
        }

        return array_values(array_unique($err));
    }

    /** Problems with one rule (config shape), in Persian. */
    public static function checkRule(array $r, string $key, array $shelves): array
    {
        $max = (int) config('shop.max_discount_percent', 25);
        $err = [];
        $name = 'تخفیف «'.($r['label'] ?? $r['name'] ?? $key).'»';
        $condLabels = DiscountRule::conditionOptions();
        if (empty($r['label']) && empty($r['name'])) {
            $err[] = "تخفیف «{$key}»: متنی که مشتری می‌بیند خالی است.";
        }
        foreach ($r['conditions'] ?? [] as $c) {
            $t = $c[0] ?? '';
            if (! isset($condLabels[$t])) {
                $err[] = "$name: شرط «{$t}» شناخته نیست.";

                continue;
            }
            $label = $condLabels[$t]['label'];
            if (isset($c['min'], $c['max']) && $c['max'] < $c['min']) {
                $err[] = "$name: در شرط «{$label}» حداکثر از حداقل کمتر است.";
            }
            $params = array_keys($condLabels[$t]['params']);
            if (in_array('min', $params, true) && ! isset($c['min']) && ! isset($c['max'])) {
                $err[] = "$name: شرط «{$label}» حداقل یا حداکثر لازم دارد.";
            }
            foreach (['product_ids' => 'products', 'category_ids' => 'categories', 'collection_ids' => 'collections'] as $db => $slug) {
                $i = array_search($db, $params, true);
                if ($i !== false) {
                    $params[$i] = $slug;
                }
            }
            foreach (array_intersect($params, self::LIST_PARAMS) as $p) {
                if (empty($c[$p])) {
                    $err[] = "$name: شرط «{$label}» هنوز چیزی انتخاب نکرده است.";
                }
            }
            if ($t === 'coupon_code' && empty($c['code'])) {
                $err[] = "$name: کد شرط «{$label}» خالی است.";
            }
            if ($t === 'item_count_from_shelf' && ! in_array($c['shelf'] ?? '', $shelves, true)) {
                $err[] = "$name: صفحهٔ «".($c['shelf'] ?? '')."» وجود ندارد.";
            }
        }
        if (empty($r['actions'])) {
            $err[] = "$name: هیچ تخفیفی تعریف نشده.";
        }
        foreach ($r['actions'] ?? [] as $a) {
            $t = $a[0] ?? '';
            $v = (int) ($a[1] ?? 0);
            if (! in_array($t, self::actions(), true)) {
                $err[] = "$name: نوع تخفیف «{$t}» شناخته نیست.";
            } elseif (in_array($t, self::PERCENT, true) && ($v < 1 || $v > $max)) {
                $err[] = "$name: درصد تخفیف باید بین ۱ و {$max} باشد.";
            } elseif (in_array($t, self::SURCHARGE_PERCENT, true) && ($v < 1 || $v > 100)) {
                $err[] = "$name: درصد افزایش باید بین ۱ و ۱۰۰ باشد.";
            } elseif (in_array($t, ['cart_discount_fixed', 'item_discount_fixed', 'discount_cap', 'item_surcharge_fixed', 'cart_surcharge_fixed'], true) && $v < 1) {
                $err[] = "$name: مبلغ باید بیشتر از صفر باشد.";
            } elseif ($t === 'block_purchase' && empty($a['message'])) {
                $err[] = "$name: پیام ممانعت از خرید خالی است.";
            }
        }
        if (isset($r['apply_mode']) && ! in_array($r['apply_mode'], [DiscountRule::APPLY_MODE_ALL, DiscountRule::APPLY_MODE_ANY], true)) {
            $err[] = "$name: حالت شرط‌ها نادرست است.";
        }
        if (isset($r['stack_mode']) && ! in_array($r['stack_mode'], [DiscountRule::STACK_MODE_ALL, DiscountRule::STACK_MODE_BEST], true)) {
            $err[] = "$name: حالت تخفیف‌ها نادرست است.";
        }
        foreach (['max_uses', 'max_uses_per_user'] as $k) {
            if (isset($r[$k]) && (int) $r[$k] < 1) {
                $err[] = "$name: سقف استفاده باید دست‌کم ۱ باشد (یا خالی).";
            }
        }

        return $err;
    }

    /** A config-shape condition with slugs → the DB shape the engine reads (ids). */
    public static function resolveCondition(array $c): array
    {
        $out = ['type' => $c[0]] + array_diff_key($c, [0 => 1, 'categories' => 1, 'products' => 1, 'collections' => 1]);
        if (isset($c['categories'])) {
            $out['category_ids'] = Category::whereIn('slug', (array) $c['categories'])->pluck('id')->all();
        }
        if (isset($c['products'])) {
            $out['product_ids'] = Product::whereIn('slug', (array) $c['products'])->pluck('id')->all();
        }
        if (isset($c['collections'])) {
            $out['collection_ids'] = ShopCollection::whereIn('slug', (array) $c['collections'])->pluck('id')->all();
        }

        return $out;
    }

    /** A DB rule as an editor rule (ids → slugs), for discounts outside the plan. */
    private static function dbRuleToEditor(DiscountRule $r): array
    {
        $conds = array_map(function ($c) {
            $out = Arr::except((array) $c, ['category_ids', 'product_ids', 'collection_ids']);
            if (isset($c['category_ids'])) {
                $out['categories'] = Category::whereIn('id', (array) $c['category_ids'])->pluck('slug')->all();
            }
            if (isset($c['product_ids'])) {
                $out['products'] = Product::whereIn('id', (array) $c['product_ids'])->pluck('slug')->all();
            }
            if (isset($c['collection_ids'])) {
                $out['collections'] = ShopCollection::whereIn('id', (array) $c['collection_ids'])->pluck('slug')->all();
            }

            return $out;
        }, (array) $r->conditions);

        return [
            'id' => $r->id, 'label' => $r->name, 'is_active' => (bool) $r->is_active, 'used_count' => (int) $r->used_count,
            'conditions' => array_values($conds),
            'actions' => array_values(array_map(fn ($a) => ['type' => $a['type'] ?? '', 'value' => (int) ($a['value'] ?? 0)] + Arr::except((array) $a, ['type', 'value']), (array) $r->actions)),
            'priority' => (int) $r->priority, 'apply_mode' => $r->apply_mode ?: DiscountRule::APPLY_MODE_ALL, 'stack_mode' => $r->stack_mode ?: DiscountRule::STACK_MODE_ALL,
            'max_uses' => $r->max_uses, 'max_uses_per_user' => $r->max_uses_per_user, 'exclusive' => (bool) $r->exclusive,
            'from' => self::tehranDate($r->starts_at), 'to' => self::tehranDate($r->expires_at),
        ];
    }

    // ── editor payload ──────────────────────────────────────────────────────

    public static function editorPayload(): array
    {
        $plan = config('campaigns', []);
        $codes = array_keys($plan['coupons'] ?? []);

        return [
            'edited' => self::edited(),
            // StoqS manages this site's discounts: these sections are read-only in the editor
            'locked' => self::stoqsManaged() ? self::STOQS_SECTIONS : [],
            'locked_message' => self::stoqsManaged() ? self::STOQS_LOCKED : null,
            'plan' => self::toEditor($plan),
            'limits' => [
                'max_percent' => (int) config('shop.max_discount_percent', 25),
                'min_margin' => Margin::min(),
                'outlet_max' => (int) config('shop.outlet_max_percent', 30),
                'no_discount_categories' => array_values(array_filter(array_map('trim', (array) (is_array($n = config('shop.no_discount_categories', [])) ? $n : explode(',', (string) $n))))),
            ],
            'catalog' => [
                'categories' => Category::orderBy('name')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->all(),
                'collections' => ShopCollection::orderBy('name')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->all(),
                'products' => self::productRows(),
                'shelf_products' => self::shelfProducts(),
                'brands' => self::named(Product::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand')->all()),
                'groups' => self::named(\App\Models\User::whereNotNull('group')->where('group', '!=', '')->distinct()->orderBy('group')->pluck('group')->all()),
                'payment_methods' => \App\Models\PaymentMethod::orderBy('id')->get()->map(fn ($m) => ['slug' => $m->key, 'name' => $m->name ?: $m->key])->all(),
                'shipping_methods' => \App\Models\ShippingMethod::orderBy('position')->get()->map(fn ($m) => ['slug' => (int) $m->id, 'name' => $m->name])->all(),
            ],
            'options' => [
                'conditions' => collect(DiscountRule::conditionOptions())->map(fn ($o) => $o['label'])->all(),
                'actions' => collect(DiscountRule::actionOptions())->map(fn ($o) => $o['label'])->all(),
            ],
            // Discounts outside the plan (made in the shop admin): editable one by one.
            'extra' => [
                'rules' => DiscountRule::where(fn ($q) => $q->whereNull('description')->orWhere('description', 'not like', 'campaign:%'))
                    ->orderByDesc('is_active')->orderByDesc('priority')->orderBy('id')->get()->map(fn ($r) => self::dbRuleToEditor($r))->all(),
                'coupons' => Coupon::whereNotIn('code', $codes ?: ['__none__'])->orderByDesc('is_active')->orderBy('code')->get()->map(fn ($c) => [
                    'id' => $c->id, 'code' => $c->code, 'description' => $c->description, 'type' => $c->type, 'value' => (int) $c->value,
                    'min_subtotal' => (int) $c->min_subtotal, 'usage_limit' => $c->usage_limit, 'per_user_limit' => $c->per_user_limit,
                    'first_order_only' => (bool) $c->first_order_only, 'exclusive' => (bool) $c->exclusive, 'used_count' => (int) $c->used_count, 'is_active' => (bool) $c->is_active,
                    'starts_at' => self::tehranDate($c->starts_at), 'expires_at' => self::tehranDate($c->expires_at),
                ])->all(),
            ],
        ];
    }

    /**
     * Every product with what the margin sheets need: price now, the price the
     * outlet works from, finished cost, outlet percent, and whether it is
     * winter (never discounted).
     */
    private static function productRows(): array
    {
        $skip = (array) config('shop.no_discount_categories', []);
        $outlet = Outlet::percents();
        $costs = Margin::costs();

        return Product::where('is_active', true)->where('is_bundle', false)->with(['category', 'categories', 'variants'])->orderBy('name')->get()
            ->map(fn ($p) => [
                'slug' => $p->slug, 'name' => $p->name, 'price' => (int) $p->price,
                'base' => Outlet::base($p), 'cost' => $costs[$p->id] ?? null, 'outlet' => $outlet[$p->id] ?? null,
                'winter' => $skip !== [] && $p->categories->pluck('slug')->push($p->category?->slug)->intersect($skip)->isNotEmpty(),
                'category' => $p->category?->name, 'stock' => (int) $p->variants->where('is_active', true)->sum('stock_qty'),
                // every category slug the product is in (StoqS's import turns a category rule into products)
                'categories' => $p->categories->pluck('slug')->push($p->category?->slug)->filter()->unique()->values()->all(),
            ])->all();
    }

    /**
     * The products on each shelf of the plan today ({shelf slug: [product slugs]}), live or not:
     * a shelf is a query (categories, outlet, stock), so StoqS's import can only take a snapshot.
     */
    private static function shelfProducts(): array
    {
        $out = [];
        foreach ((array) config('campaigns.shelves', []) as $slug => $s) {
            if (! is_array($s)) {
                continue;
            }
            try {
                $q = Product::query()->where('is_active', true);
                Campaigns::applyShelf($q, $s + ['slug' => (string) $slug]);
                $out[(string) $slug] = $q->limit(2000)->pluck('slug')->all();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    private static function named(array $values): array
    {
        return array_values(array_map(fn ($v) => ['slug' => $v, 'name' => $v], $values));
    }

    private static function tehranDate($at): ?string
    {
        return $at ? Carbon::parse($at)->setTimezone('Asia/Tehran')->toDateString() : null;
    }

    // ── single DB discounts outside the plan ────────────────────────────────

    /** Create ($rule null) or replace a shop-admin rule from an editor rule. */
    public static function saveRule(?DiscountRule $rule, array $d): array
    {
        if (self::stoqsManaged()) {
            return [self::STOQS_LOCKED];
        }
        if ($rule && str_starts_with((string) $rule->description, 'campaign:')) {
            return ['این تخفیف بخشی از برنامهٔ کمپین‌هاست؛ آن را در خود کمپین ویرایش کنید.'];
        }
        $r = self::ruleFromEditor(Arr::except($d, ['id', 'used_count', 'campaign']));
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
        $errors = self::checkRule($r, (string) ($rule?->id ?? 'new'), array_keys(config('campaigns.shelves', [])));
        foreach (['from', 'to'] as $k) {
            if (isset($r[$k]) && ! $date($r[$k])) {
                $errors[] = 'تاریخ «'.$r[$k].'» درست نیست.';
            }
        }
        if ($errors) {
            return $errors;
        }
        $rule ??= new DiscountRule(['description' => 'nj-focus', 'used_count' => 0]);
        $rule->fill([
            'name' => mb_substr((string) $r['label'], 0, 190),
            'priority' => (int) ($r['priority'] ?? 10),
            'conditions' => array_map(fn ($c) => self::resolveCondition($c), $r['conditions']),
            'actions' => array_map(fn ($a) => ['type' => $a[0], 'value' => $a[1] ?? 0] + Arr::except($a, [0, 1]), $r['actions']),
            'apply_mode' => $r['apply_mode'] ?? DiscountRule::APPLY_MODE_ALL,
            'stack_mode' => $r['stack_mode'] ?? DiscountRule::STACK_MODE_ALL,
            'max_uses' => $r['max_uses'] ?? null,
            'max_uses_per_user' => $r['max_uses_per_user'] ?? null,
            'exclusive' => (bool) ($r['exclusive'] ?? false),
            'starts_at' => self::fromTehran($r['from'] ?? null, false),
            'expires_at' => self::fromTehran($r['to'] ?? null, true),
            'is_active' => (bool) ($r['is_active'] ?? true),
        ])->save();

        return [];
    }

    public static function deleteRule(DiscountRule $rule): array
    {
        if (self::stoqsManaged()) {
            return [self::STOQS_LOCKED];
        }
        if (str_starts_with((string) $rule->description, 'campaign:')) {
            return ['این تخفیف بخشی از برنامهٔ کمپین‌هاست؛ آن را در خود کمپین حذف کنید.'];
        }
        $rule->delete();

        return [];
    }

    public static function createCoupon(array $d): array
    {
        if (self::stoqsManaged()) {
            return [self::STOQS_LOCKED];
        }
        $code = strtoupper(trim((string) ($d['code'] ?? '')));
        if (! preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
            return ['کد: فقط حروف بزرگ انگلیسی و عدد، ۳ تا ۳۰ حرف.'];
        }
        if (Coupon::where('code', $code)->exists()) {
            return ["کد «{$code}» از قبل وجود دارد."];
        }
        $coupon = new Coupon(['code' => $code, 'type' => 'percent', 'value' => 0, 'is_active' => true]);
        $errors = self::updateCoupon($coupon, $d + ['type' => 'percent']);
        if ($errors && $coupon->exists) {
            $coupon->delete();
        }

        return $errors;
    }

    public static function deleteCoupon(Coupon $coupon): array
    {
        if (self::stoqsManaged()) {
            return [self::STOQS_LOCKED];
        }
        if (array_key_exists($coupon->code, config('campaigns.coupons', []))) {
            return ['این کد بخشی از برنامهٔ کمپین‌هاست؛ آن را در خود کمپین حذف کنید.'];
        }
        if ($coupon->used_count > 0) {
            $coupon->update(['is_active' => false]);   // used codes stay for the order history

            return [];
        }
        $coupon->delete();

        return [];
    }

    public static function updateCoupon(Coupon $coupon, array $d): array
    {
        if (self::stoqsManaged()) {
            return [self::STOQS_LOCKED];
        }
        if (array_key_exists($coupon->code, config('campaigns.coupons', []))) {
            return ['این کد بخشی از برنامهٔ کمپین‌هاست؛ آن را در خود کمپین ویرایش کنید.'];
        }
        $max = (int) config('shop.max_discount_percent', 25);
        $type = $d['type'] ?? $coupon->type;
        $value = (int) ($d['value'] ?? $coupon->value);
        if ($type === 'percent' && ($value < 1 || $value > $max)) {
            return ["درصد تخفیف باید بین ۱ و {$max} باشد."];
        }
        $int = fn ($k) => array_key_exists($k, $d) ? (($d[$k] === null || $d[$k] === '') ? null : max(0, (int) $d[$k])) : $coupon->{$k};
        $coupon->fill([
            'description' => array_key_exists('description', $d) ? (trim((string) $d['description']) ?: null) : $coupon->description,
            'type' => in_array($type, ['percent', 'fixed', 'free_shipping'], true) ? $type : $coupon->type,
            'value' => $value,
            'min_subtotal' => (int) ($int('min_subtotal') ?? 0),
            'usage_limit' => $int('usage_limit'),
            'per_user_limit' => $int('per_user_limit'),
            'first_order_only' => (bool) ($d['first_order_only'] ?? $coupon->first_order_only),
            'exclusive' => (bool) ($d['exclusive'] ?? $coupon->exclusive),
            'is_active' => (bool) ($d['is_active'] ?? $coupon->is_active),
            'starts_at' => array_key_exists('starts_at', $d) ? self::fromTehran($d['starts_at'], false) : $coupon->starts_at,
            'expires_at' => array_key_exists('expires_at', $d) ? self::fromTehran($d['expires_at'], true) : $coupon->expires_at,
        ])->save();

        return [];
    }

    private static function fromTehran(?string $day, bool $end): ?Carbon
    {
        if (! $day || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }
        $t = Carbon::parse($day, 'Asia/Tehran');

        return ($end ? $t->endOfDay() : $t->startOfDay())->setTimezone(config('app.timezone'));
    }
}
