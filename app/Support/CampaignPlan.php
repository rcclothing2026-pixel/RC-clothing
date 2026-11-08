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
    public const KEYED = ['campaigns', 'rules', 'coupons', 'bundles', 'sets', 'popups', 'shelves'];

    public const LISTS = ['slides', 'badges'];

    public const CONDITIONS = [
        'cart_subtotal', 'cart_subtotal_excluding_sale', 'cart_item_count', 'user_previous_order_count', 'user_previous_order_total',
        'user_status', 'item_products', 'item_categories', 'item_collections', 'has_full_price_item',
        'item_count_from_products', 'item_total_from_products', 'item_count_from_categories', 'item_total_from_categories',
        'item_count_from_collections', 'item_total_from_collections', 'item_count_from_shelf',
    ];

    public const ACTIONS = ['cart_discount_percent', 'cart_discount_fixed', 'item_discount_percent', 'item_discount_fixed', 'discount_cap', 'free_shipping'];

    private const PERCENT = ['cart_discount_percent', 'item_discount_percent'];

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

    public static function reset(): void
    {
        $old = config('campaigns', []);
        DB::table('campaign_plans')->where('id', 1)->delete();
        config(['campaigns' => config('campaigns_file')]);
        self::retire($old, config('campaigns'));
        Artisan::call('campaigns:sync');
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

    private static function ruleToEditor(array $r): array
    {
        $r['conditions'] = array_map(fn ($c) => ['type' => $c[0] ?? ''] + Arr::except($c, [0]), $r['conditions'] ?? []);
        $r['actions'] = array_map(fn ($a) => ['type' => $a[0] ?? '', 'value' => $a[1] ?? 0], $r['actions'] ?? []);

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
                    $item['conditions'] = array_values(array_map(fn ($c) => [(string) ($c['type'] ?? '')] + self::clean(Arr::except((array) $c, ['type'])), (array) ($item['conditions'] ?? [])));
                    $item['actions'] = array_values(array_map(fn ($a) => [(string) ($a['type'] ?? ''), (int) ($a['value'] ?? 0)], (array) ($item['actions'] ?? [])));
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

    /** Drop empty strings, nulls and empty lists; numeric strings become numbers. */
    private static function clean(array $item): array
    {
        $out = [];
        foreach ($item as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (is_string($v) && in_array($k, ['min', 'max', 'value', 'off', 'delay', 'max_stock', 'max_price', 'min_subtotal', 'max_subtotal', 'max_discount', 'usage_limit', 'per_user_limit', 'priority'], true) && is_numeric($v)) {
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

        $label = ['rules' => 'تخفیف', 'coupons' => 'کد', 'bundles' => 'بسته', 'sets' => 'ست', 'popups' => 'پاپ‌آپ', 'shelves' => 'صفحه', 'slides' => 'نوار بالا', 'badges' => 'برچسب'];
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
                if (in_array($s, ['rules', 'bundles', 'sets', 'popups', 'shelves'], true) && ! $slug($k)) {
                    $err[] = "$name: شناسه فقط حروف کوچک انگلیسی، عدد و خط تیره باشد.";
                }
            }
        }

        foreach ($plan['rules'] ?? [] as $k => $r) {
            $name = 'تخفیف «'.($r['label'] ?? $k).'»';
            if (empty($r['label'])) {
                $err[] = "تخفیف «{$k}»: متنی که مشتری می‌بیند خالی است.";
            }
            foreach ($r['conditions'] ?? [] as $c) {
                if (! in_array($c[0] ?? '', self::CONDITIONS, true)) {
                    $err[] = "$name: شرط «".($c[0] ?? '').'» شناخته نیست.';
                }
                if (isset($c['min'], $c['max']) && $c['max'] < $c['min']) {
                    $err[] = "$name: حداکثر شرط از حداقلش کمتر است.";
                }
            }
            if (empty($r['actions'])) {
                $err[] = "$name: هیچ تخفیفی تعریف نشده.";
            }
            foreach ($r['actions'] ?? [] as $a) {
                if (! in_array($a[0] ?? '', self::ACTIONS, true)) {
                    $err[] = "$name: نوع تخفیف «".($a[0] ?? '').'» شناخته نیست.';
                } elseif (in_array($a[0], self::PERCENT, true) && ($a[1] < 1 || $a[1] > $max)) {
                    $err[] = "$name: درصد تخفیف باید بین ۱ و {$max} باشد.";
                } elseif ($a[0] !== 'free_shipping' && $a[1] < 1) {
                    $err[] = "$name: مبلغ تخفیف باید بیشتر از صفر باشد.";
                }
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

    // ── editor payload ──────────────────────────────────────────────────────

    public static function editorPayload(): array
    {
        $plan = config('campaigns', []);
        $codes = array_keys($plan['coupons'] ?? []);

        return [
            'edited' => self::edited(),
            'plan' => self::toEditor($plan),
            'limits' => [
                'max_percent' => (int) config('shop.max_discount_percent', 25),
                'no_discount_categories' => array_values(array_filter(array_map('trim', (array) (is_array($n = config('shop.no_discount_categories', [])) ? $n : explode(',', (string) $n))))),
            ],
            'catalog' => [
                'categories' => Category::orderBy('name')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->all(),
                'collections' => ShopCollection::orderBy('name')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->all(),
                'products' => Product::where('is_active', true)->where('is_bundle', false)->orderBy('name')->get(['slug', 'name', 'price'])
                    ->map(fn ($p) => ['slug' => $p->slug, 'name' => $p->name, 'price' => (int) $p->price])->all(),
            ],
            // Discounts outside the plan (made in the shop admin): editable one by one.
            'extra' => [
                'rules' => DiscountRule::where(fn ($q) => $q->whereNull('description')->orWhere('description', 'not like', 'campaign:%'))
                    ->orderByDesc('is_active')->orderBy('priority')->get()->map(fn ($r) => [
                        'id' => $r->id, 'name' => $r->name, 'is_active' => (bool) $r->is_active,
                        'starts_at' => self::tehranDate($r->starts_at), 'expires_at' => self::tehranDate($r->expires_at),
                        'conditions' => self::describeConditions((array) $r->conditions),
                        'actions' => array_map(fn ($a) => ['type' => $a['type'] ?? '', 'value' => (int) ($a['value'] ?? 0)], (array) $r->actions),
                        'used_count' => (int) $r->used_count,
                    ])->all(),
                'coupons' => Coupon::whereNotIn('code', $codes ?: ['__none__'])->orderByDesc('is_active')->orderBy('code')->get()->map(fn ($c) => [
                    'id' => $c->id, 'code' => $c->code, 'description' => $c->description, 'type' => $c->type, 'value' => (int) $c->value,
                    'min_subtotal' => (int) $c->min_subtotal, 'usage_limit' => $c->usage_limit, 'per_user_limit' => $c->per_user_limit,
                    'first_order_only' => (bool) $c->first_order_only, 'used_count' => (int) $c->used_count, 'is_active' => (bool) $c->is_active,
                    'starts_at' => self::tehranDate($c->starts_at), 'expires_at' => self::tehranDate($c->expires_at),
                ])->all(),
            ],
        ];
    }

    private static function tehranDate($at): ?string
    {
        return $at ? Carbon::parse($at)->setTimezone('Asia/Tehran')->toDateString() : null;
    }

    /** Short Persian lines for a DB rule's conditions (ids shown as names). */
    private static function describeConditions(array $conds): array
    {
        $labels = DiscountRule::conditionOptions();

        return array_map(function ($c) use ($labels) {
            $t = $labels[$c['type'] ?? '']['label'] ?? ($c['type'] ?? '?');
            $range = trim((isset($c['min']) ? 'از '.number_format((int) $c['min']) : '').(isset($c['max']) ? ' تا '.number_format((int) $c['max']) : ''));
            $names = array_merge(
                isset($c['category_ids']) ? Category::whereIn('id', (array) $c['category_ids'])->pluck('name')->all() : [],
                isset($c['product_ids']) ? Product::whereIn('id', (array) $c['product_ids'])->pluck('name')->all() : [],
            );

            return trim($t.' '.$range.($names ? ': '.implode('، ', $names) : ''));
        }, $conds);
    }

    // ── single DB discounts outside the plan ────────────────────────────────

    public static function updateRule(DiscountRule $rule, array $d): array
    {
        if (str_starts_with((string) $rule->description, 'campaign:')) {
            return ['این تخفیف بخشی از برنامهٔ کمپین‌هاست؛ آن را در خود کمپین ویرایش کنید.'];
        }
        $max = (int) config('shop.max_discount_percent', 25);
        $actions = (array) $rule->actions;
        foreach ((array) ($d['actions'] ?? []) as $i => $a) {
            if (! isset($actions[$i])) {
                continue;
            }
            $v = (int) ($a['value'] ?? 0);
            if (in_array($actions[$i]['type'] ?? '', self::PERCENT, true) && ($v < 1 || $v > $max)) {
                return ["درصد تخفیف باید بین ۱ و {$max} باشد."];
            }
            $actions[$i]['value'] = $v;
        }
        $rule->fill([
            'name' => isset($d['name']) && trim($d['name']) !== '' ? mb_substr(trim($d['name']), 0, 190) : $rule->name,
            'is_active' => (bool) ($d['is_active'] ?? $rule->is_active),
            'actions' => $actions,
            'starts_at' => array_key_exists('starts_at', $d) ? self::fromTehran($d['starts_at'], false) : $rule->starts_at,
            'expires_at' => array_key_exists('expires_at', $d) ? self::fromTehran($d['expires_at'], true) : $rule->expires_at,
        ])->save();

        return [];
    }

    public static function updateCoupon(Coupon $coupon, array $d): array
    {
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
