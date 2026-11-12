<?php

namespace App\Services\Discount;

/**
 * StoqS's discount engine, schema 2, for a shop whose promotions StoqS manages.
 *
 * A port of the pure part of StoqS's includes/discount-engine.php (de_evaluate and its
 * helpers, each de_x() here as self::x()), made mechanically and unchanged otherwise,
 * so one cart gives the same discounts here, in StoqS and in the WooCommerce plugin.
 * It reads StoqS's rule, code and line shapes (the /promotions payload), never the
 * shop's own DiscountRule / Coupon rows: those keep their own engine
 * (DiscountRuleEngine + Cart) and run whenever StoqS does not manage this site.
 *
 * tests/Feature/StoqsConformanceTest.php runs StoqS's pinned conformance fixtures
 * (tests/Fixtures/discount-conformance) against it. Do not edit it by hand: port it
 * again from StoqS and run that test.
 */
final class StoqsEngine
{
    public const SCHEMA = 3;

    /** Every condition the engine knows, with its Persian label (the shops' own labels). */
    public static function condition_types(): array {
        return [
            'cart_subtotal' => 'مبلغ خرید',
            'cart_subtotal_excluding_sale' => 'مبلغ خرید غیر فروش ویژه',
            'cart_item_count' => 'تعداد اقلام سفارش',
            'cart_weight' => 'وزن کالاهای سفارش',
            'coupon_code' => 'کوپن تخفیف',
            'coupon_single_use' => 'کوپن یکبار مصرف',
            'coupon_single_use_per_user' => 'کوپن یکبار مصرف برای هر کاربر',
            'item_products' => 'محصول خریداری شده',
            'item_brands' => 'برند محصول',
            'item_categories' => 'بخش محصول',
            'item_collections' => 'مجموعه محصول',
            'has_featured' => 'حاوی محصول ویژه',
            'has_sale_item' => 'حاوی محصول فروش ویژه',
            'has_discounted_item' => 'حاوی محصول تخفیف خورده',
            'has_full_price_item' => 'حاوی محصول بدون تخفیف',
            'item_count_from_products' => 'تعداد اقلام از محصولات مشخص',
            'item_total_from_products' => 'مبلغ اقلام از محصولات مشخص',
            'item_count_from_brands' => 'تعداد اقلام از برندهای مشخص',
            'item_total_from_brands' => 'قیمت اقلام از برندهای مشخص',
            'item_count_from_categories' => 'تعداد اقلام از بخش‌های مشخص',
            'item_total_from_categories' => 'قیمت اقلام از بخش‌های مشخص',
            'item_count_from_collections' => 'تعداد اقلام از مجموعه‌های مشخص',
            'item_count_from_shelf' => 'تعداد اقلام از قفسهٔ کمپین',
            'item_total_from_collections' => 'قیمت اقلام از مجموعه‌های مشخص',
            'user_status' => 'وضعیت کاربر',
            'user_group' => 'گروه کاربری',
            'user_device' => 'دستگاه کاربر',
            'user_previous_order_count' => 'خرید قبلی کاربر (تعداد)',
            'user_previous_order_total' => 'مبلغ خرید قبلی کاربر',
            'user_phone' => 'شمارهٔ موبایل کاربر',
            'user_city' => 'شهر کاربر',
            'order_payment_method' => 'روش پرداخت سفارش',
            'order_shipping_method' => 'روش ارسال سفارش',
        ];
    }

    public static function action_types(): array {
        return [
            'cart_discount_percent' => 'تخفیف روی سبد به درصد',
            'cart_discount_fixed' => 'تخفیف روی سبد به تومان',
            'item_discount_percent' => 'تخفیف روی کالا(ها) به درصد',
            'item_discount_fixed' => 'تخفیف روی کالا(ها) به تومان',
            'discount_cap' => 'سقف تخفیف',
            'cost_cap' => 'سقف هزینه',
            'block_purchase' => 'ممانعت از خرید',
            'free_shipping' => 'ارسال رایگان',
            'item_surcharge_percent' => 'افزایش هزینه روی کالاها به درصد',
            'item_surcharge_fixed' => 'افزایش هزینه روی کالاها به تومان',
            'cart_surcharge_fixed' => 'افزایش هزینه روی سبد به تومان',
            'cart_surcharge_percent' => 'افزایش هزینه روی سبد به درصد',
        ];
    }

    /** Where a rule or a code may run. 'concept' is off unless chosen, like campaigns. */
    public static function channels(): array {
        return ['web' => 'وب‌سایت', 'brand' => 'صندوق برند', 'stores' => 'فروشگاه‌ها', 'concept' => 'کانسپت‌ها'];
    }

    /** A channel list (array or CSV) as a clean CSV of known channels, in a fixed order. */
    public static function channels_csv($raw): string {
        $in = is_array($raw) ? $raw : explode(',', (string) $raw);
        $in = array_map(fn($c) => strtolower(trim((string) $c)), $in);
        return implode(',', array_values(array_filter(array_keys(self::channels()), fn($c) => in_array($c, $in, true))));
    }

    /** No channel asked, or the list names it. An empty list runs nowhere. */
    public static function channel_ok(string $csv, ?string $channel): bool {
        if ($channel === null || $channel === '') return true;
        return in_array($channel, array_filter(explode(',', $csv)), true);
    }

    /** A stored id list (JSON array, CSV or array) as unique positive ints. */
    public static function ids($raw): array {
        if ($raw === null || $raw === '') return [];
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            $raw = is_array($j) ? $j : explode(',', $raw);
        }
        if (!is_array($raw)) $raw = [$raw];
        $out = [];
        foreach ($raw as $v) { $n = (int) $v; if ($n > 0) $out[$n] = $n; }
        return array_values($out);
    }

    /** A code as the lookup sees it: trimmed, upper-case, Persian/Arabic digits as Latin. */
    public static function code_norm(string $code): string {
        $code = strtr(trim($code), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                                    '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        return mb_strtoupper($code, 'UTF-8');
    }

    /** Digits only (Persian digits too), as customers.phone stores it. */
    public static function phone_norm(?string $phone): string {
        if ($phone === null) return '';
        return (string) preg_replace('/\D+/', '', self::code_norm($phone));
    }

    public static function fa($n): string {
        return strtr((string) $n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }

    /** The shops' Margin::floor(): ceil(cost × 100 / (100 − min margin)). Null without a cost. */
    public static function floor_from_cost($cost, float $min_margin): ?float {
        if ($cost === null || $cost === '' || (float) $cost <= 0) return null;
        $m = max(0.0, min(90.0, $min_margin));
        return (float) ceil(round((float) $cost * 100 / (100 - $m), 6));
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Normalising what comes in. Every caller (API, admin, till, tests) goes through
    // these, so a rule row and a rule from JSON look the same to the engine.
    // ─────────────────────────────────────────────────────────────────────────────

    public static function rule_normalize(array $r): array {
        $conds = $r['conditions'] ?? [];
        if (is_string($conds)) $conds = json_decode($conds, true);
        $acts = $r['actions'] ?? [];
        if (is_string($acts)) $acts = json_decode($acts, true);
        $group = $r['group'] ?? ($r['group_key'] ?? null);
        return [
            'id'          => (int) ($r['id'] ?? 0),
            'label'       => (string) ($r['label'] ?? ($r['name'] ?? '')),
            'conditions'  => is_array($conds) ? array_values(array_filter($conds, 'is_array')) : [],
            'actions'     => is_array($acts) ? array_values(array_filter($acts, 'is_array')) : [],
            'apply_mode'  => ($r['apply_mode'] ?? 'all') === 'any' ? 'any' : 'all',
            'stack_mode'  => ($r['stack_mode'] ?? 'all') === 'best' ? 'best' : 'all',
            'priority'    => (int) ($r['priority'] ?? 0),
            'exclusive'   => !empty($r['exclusive']),
            'group'       => ($group === null || $group === '') ? null : (string) $group,
            'channels'    => self::channels_csv($r['channels'] ?? 'web,brand,stores'),
            'starts_at'   => ($r['starts_at'] ?? null) ?: null,
            'ends_at'     => ($r['ends_at'] ?? ($r['expires_at'] ?? null)) ?: null,
            'enabled'     => !array_key_exists('enabled', $r) || !empty($r['enabled']),
            'max_uses'    => isset($r['max_uses']) && $r['max_uses'] !== '' && $r['max_uses'] !== null ? (int) $r['max_uses'] : null,
            'uses'        => (int) ($r['uses'] ?? ($r['used_count'] ?? 0)),
            'campaign_id' => !empty($r['campaign_id']) ? (int) $r['campaign_id'] : null,
        ];
    }

    public static function code_normalize(array $c): array {
        $type = (string) ($c['type'] ?? 'percent');
        if ($type === 'fixed') $type = 'toman';            // the shops call it «fixed»
        if (!in_array($type, ['percent', 'toman', 'free_shipping'], true)) $type = 'percent';
        $num = fn($k) => isset($c[$k]) && $c[$k] !== '' && $c[$k] !== null ? (float) $c[$k] : null;
        $int = fn($k) => isset($c[$k]) && $c[$k] !== '' && $c[$k] !== null ? (int) $c[$k] : null;
        return [
            'id'                   => (int) ($c['id'] ?? 0),
            'code'                 => (string) ($c['code'] ?? ''),
            'label'                => (string) ($c['label'] ?? ($c['description'] ?? '')),
            'type'                 => $type,
            'value'                => (float) ($c['value'] ?? 0),
            'min_subtotal'         => (float) ($c['min_subtotal'] ?? 0),
            'max_subtotal'         => $num('max_subtotal'),
            'max_discount'         => $num('max_discount'),
            'starts_at'            => ($c['starts_at'] ?? null) ?: null,
            'ends_at'              => ($c['ends_at'] ?? ($c['expires_at'] ?? null)) ?: null,
            'usage_limit'          => $int('usage_limit'),
            'per_customer_limit'   => $int('per_customer_limit') ?? $int('per_user_limit'),
            'first_order_only'     => !empty($c['first_order_only']),
            'individual_use'       => !empty($c['individual_use']) || !empty($c['exclusive']),
            // schema 2: an «alone» code measured against the list price (only with individual_use)
            'vs_list'              => !empty($c['vs_list']),
            'include_product_ids'  => self::ids($c['include_product_ids'] ?? null),
            'exclude_product_ids'  => self::ids($c['exclude_product_ids'] ?? null),
            'include_category_ids' => self::ids($c['include_category_ids'] ?? null),
            'exclude_category_ids' => self::ids($c['exclude_category_ids'] ?? null),
            'channels'             => self::channels_csv($c['channels'] ?? 'web,brand,stores'),
            'enabled'              => !array_key_exists('enabled', $c) || !empty($c['enabled']),
            'uses'                 => (int) ($c['uses'] ?? ($c['used_count'] ?? 0)),
        ];
    }

    /**
     * One cart line. Required: qty, unit_price, product_id. Optional, for the conditions that
     * read them: category_ids (primary included), collection_id, brand, weight, featured,
     * on_sale (the product already has a sale price), shelves; discountable (default true:
     * the shops' bundles / winter / outlet are not); floor_price, or cost + min_margin;
     * list_price (schema 2: the price before the campaign; null/absent = the cap is on
     * unit_price exactly as before; below unit_price it counts as unit_price).
     * Schema 3: cap_exempt (default false; sent only for an item «اجازهٔ عبور از سقف» priced by a
     * campaign stacked on the outlet, «روی هم»: the line's room for the discounts aimed at it is
     * its whole total, no % cap and no floor, but what other lines cannot take never spills onto
     * it past its normal room); count_in_tiers (default true; false = its pieces
     * are not counted by any count condition: an outlet item set to «فقط اوتلت»).
     */
    public static function line_normalize(array $l, int $i, float $min_margin = 10.0): array {
        $qty  = (int) ($l['qty'] ?? ($l['quantity'] ?? 0));
        $unit = (float) ($l['unit_price'] ?? 0);
        $floor = array_key_exists('floor_price', $l)
            ? ($l['floor_price'] === null || $l['floor_price'] === '' ? null : (float) $l['floor_price'])
            : (isset($l['cost']) ? self::floor_from_cost($l['cost'], (float) ($l['min_margin'] ?? $min_margin)) : null);
        $cats = self::ids($l['category_ids'] ?? []);
        if (!empty($l['category_id']) && !in_array((int) $l['category_id'], $cats, true)) array_unshift($cats, (int) $l['category_id']);
        $list = isset($l['list_price']) && is_numeric($l['list_price']) && (float) $l['list_price'] > 0
            ? max((float) $l['list_price'], $unit) : null;
        return [
            'index'         => $i,
            'variant_id'    => (int) ($l['variant_id'] ?? 0),
            'product_id'    => (int) ($l['product_id'] ?? 0),
            'category_ids'  => $cats,
            'collection_id' => (int) ($l['collection_id'] ?? 0),
            'brand'         => (string) ($l['brand'] ?? ''),
            'weight'        => (int) ($l['weight'] ?? 0),
            'featured'      => !empty($l['featured']),
            'on_sale'       => !empty($l['on_sale']),
            'shelves'       => array_map('strval', (array) ($l['shelves'] ?? [])),
            'qty'           => $qty,
            'unit_price'    => $unit,
            'line_total'    => $unit * $qty,
            'discountable'  => !array_key_exists('discountable', $l) || !empty($l['discountable']),
            'floor_price'   => $floor,
            'list_price'    => $list,
            'cap_exempt'    => !empty($l['cap_exempt']),
            'pieces'        => !array_key_exists('count_in_tiers', $l) || !empty($l['count_in_tiers']) ? $qty : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // The engine (pure). Mirrors DiscountRuleEngine::evaluate + Cart::discountChoice,
    // discountCap and combinedDiscount, then splits the result over the lines.
    // ─────────────────────────────────────────────────────────────────────────────

    public static function rule_active(array $r, string $now): bool {
        if (!$r['enabled']) return false;
        if ($r['starts_at'] !== null && $r['starts_at'] > $now) return false;
        if ($r['ends_at'] !== null && $r['ends_at'] < $now) return false;
        return true;
    }

    public static function range(int $value, array $cond): bool {
        if (isset($cond['min']) && $value < (int) $cond['min']) return false;
        if (isset($cond['max']) && $value > (int) $cond['max']) return false;
        return true;
    }

    public static function line_in_cats(array $l, array $ids): bool {
        return (bool) array_intersect($l['category_ids'], $ids);
    }

    /** Σ of a line field over the lines $pick accepts. */
    public static function sum(array $lines, callable $pick, string $field): int {
        $s = 0;
        foreach ($lines as $l) if ($pick($l)) $s += $l[$field];
        return (int) $s;
    }

    /** DiscountRuleEngine::evaluateCondition, case for case. */
    public static function condition(array $c, array $lines, int $subtotal, ?array $cust, array $ctx, ?array $coupon): bool {
        $ints = fn($k) => array_map('intval', (array) ($c[$k] ?? []));
        $lower = fn($a) => array_map(fn($b) => mb_strtolower(trim((string) $b)), (array) $a);
        $byProducts = function ($l) use ($ints) { return in_array($l['product_id'], $ints('product_ids')); };
        $byBrands = function ($l) use ($c, $lower) { return in_array(mb_strtolower(trim($l['brand'])), $lower($c['brands'] ?? []), true); };
        $byCats = function ($l) use ($ints) { return self::line_in_cats($l, $ints('category_ids')); };
        $byColls = function ($l) use ($ints) { return in_array($l['collection_id'], $ints('collection_ids')); };
        $any = function (callable $f) use ($lines) { foreach ($lines as $l) if ($f($l)) return true; return false; };
        $orders = fn($k) => (int) ($cust[$k] ?? 0);
        switch ((string) ($c['type'] ?? '')) {
            case 'cart_subtotal': return self::range($subtotal, $c);
            case 'cart_subtotal_excluding_sale': return self::range(self::sum($lines, fn($l) => !$l['on_sale'], 'line_total'), $c);
            case 'cart_item_count': return self::range(self::sum($lines, fn($l) => true, 'pieces'), $c);
            case 'cart_weight':
                $w = 0; foreach ($lines as $l) $w += $l['weight'] * $l['qty'];
                return self::range((int) $w, $c);
            case 'coupon_code':
                return $coupon !== null && self::code_norm($coupon['code']) === self::code_norm((string) ($c['code'] ?? ''));
            case 'coupon_single_use': return $coupon !== null;
            case 'coupon_single_use_per_user': return $coupon !== null && $cust !== null;
            case 'item_products': return $ints('product_ids') !== [] && $any($byProducts);
            case 'item_brands': return ($c['brands'] ?? []) !== [] && $any($byBrands);
            case 'item_categories': return $ints('category_ids') !== [] && $any($byCats);
            case 'item_collections': return $ints('collection_ids') !== [] && $any($byColls);
            case 'has_featured': return $any(fn($l) => $l['featured']);
            case 'has_sale_item':
            case 'has_discounted_item': return $any(fn($l) => $l['on_sale']);
            case 'has_full_price_item': return $any(fn($l) => !$l['on_sale']);
            case 'item_count_from_products': return self::range($ints('product_ids') ? self::sum($lines, $byProducts, 'pieces') : 0, $c);
            case 'item_total_from_products': return self::range($ints('product_ids') ? self::sum($lines, $byProducts, 'line_total') : 0, $c);
            case 'item_count_from_brands': return self::range(($c['brands'] ?? []) ? self::sum($lines, $byBrands, 'pieces') : 0, $c);
            case 'item_total_from_brands': return self::range(($c['brands'] ?? []) ? self::sum($lines, $byBrands, 'line_total') : 0, $c);
            case 'item_count_from_categories': return self::range($ints('category_ids') ? self::sum($lines, $byCats, 'pieces') : 0, $c);
            case 'item_total_from_categories': return self::range($ints('category_ids') ? self::sum($lines, $byCats, 'line_total') : 0, $c);
            case 'item_count_from_shelf':
                $shelf = (string) ($c['shelf'] ?? '');
                return self::range(self::sum($lines, fn($l) => in_array($shelf, $l['shelves'], true), 'pieces'), $c);
            case 'item_count_from_collections': return self::range($ints('collection_ids') ? self::sum($lines, $byColls, 'pieces') : 0, $c);
            case 'item_total_from_collections': return self::range($ints('collection_ids') ? self::sum($lines, $byColls, 'line_total') : 0, $c);
            case 'user_status':
                $st = (string) ($c['status'] ?? 'registered');
                if ($st === 'guest') return $cust === null;
                if ($st === 'registered') return $cust !== null;
                if ($st === 'verified') return $cust !== null && !empty($cust['verified']);
                return false;
            case 'user_group':
                $g = $lower($c['group_ids'] ?? []);
                return $g !== [] && $cust !== null && in_array(mb_strtolower(trim((string) ($cust['group'] ?? ''))), $g, true);
            case 'user_device':
                $mobile = ($ctx['device'] ?? '') === 'mobile';
                return ($c['device'] ?? '') === 'mobile' ? $mobile : !$mobile;
            case 'user_previous_order_count': return self::range($cust ? $orders('previous_order_count') : 0, $c);
            case 'user_previous_order_total': return self::range($cust ? $orders('previous_order_total') : 0, $c);
            case 'user_phone':
                $mode = $c['mode'] ?? 'allow';
                if ($cust === null || empty($cust['phone'])) return $mode === 'deny';
                $match = in_array(self::phone_norm((string) $cust['phone']), array_map([self::class, 'phone_norm'], (array) ($c['phones'] ?? [])), true);
                return $mode === 'allow' ? $match : !$match;
            case 'user_city':
                $cities = $lower($c['cities'] ?? []);
                if (!$cities) return false;
                $city = (string) ($ctx['shipping_city'] ?? '') !== '' ? $ctx['shipping_city'] : ($cust['city'] ?? '');
                return $city !== '' && in_array(mb_strtolower(trim((string) $city)), $cities, true);
            case 'order_payment_method':
                $keys = (array) ($c['method_keys'] ?? []);
                return $keys && !empty($ctx['payment_method_key']) && in_array($ctx['payment_method_key'], $keys, true);
            case 'order_shipping_method':
                $ids = $ints('method_ids');
                return $ids && !empty($ctx['shipping_method_id']) && in_array((int) $ctx['shipping_method_id'], $ids, true);
        }
        return false;
    }

    /**
     * DiscountRuleEngine::itemBase's line set: the discountable lines the rule's item conditions
     * point at (products / categories / collections / shelf). Null = no item conditions, so the
     * whole discountable base. (Brand conditions do not narrow it in the shops either.)
     */
    public static function item_targets(array $rule, array $lines): ?array {
        $p = $c = $k = $s = [];
        foreach ($rule['conditions'] as $cond) {
            $t = (string) ($cond['type'] ?? '');
            if (str_ends_with($t, '_products')) $p = array_merge($p, array_map('intval', (array) ($cond['product_ids'] ?? [])));
            elseif (str_ends_with($t, '_categories')) $c = array_merge($c, array_map('intval', (array) ($cond['category_ids'] ?? [])));
            elseif (str_ends_with($t, '_collections')) $k = array_merge($k, array_map('intval', (array) ($cond['collection_ids'] ?? [])));
            elseif ($t === 'item_count_from_shelf') $s[] = (string) ($cond['shelf'] ?? '');
        }
        if (!$p && !$c && !$k && !$s) return null;
        $out = [];
        foreach ($lines as $l) {
            if (!$l['discountable']) continue;
            if (in_array($l['product_id'], $p, true) || ($c && self::line_in_cats($l, $c))
                || in_array($l['collection_id'], $k, true) || array_intersect($s, $l['shelves'])) $out[] = $l['index'];
        }
        return $out;
    }

    /** $amount spread over the lines $idx by line total: [line index => share]. */
    public static function weights(array $lines, array $idx, float $amount): array {
        $base = 0.0;
        foreach ($idx as $i) $base += $lines[$i]['line_total'];
        $w = [];
        if ($base <= 0 || $amount == 0.0) return $w;
        foreach ($idx as $i) $w[$i] = $amount * $lines[$i]['line_total'] / $base;
        return $w;
    }

    /**
     * DiscountRuleEngine::applyActions for one rule: its amount (the shops' integer, same
     * rounding), plus where on the cart that amount sits, line by line.
     */
    public static function apply_actions(array $rule, array $lines, int $subtotal, int $base, array &$res): ?array {
        $disc_idx = [];
        foreach ($lines as $l) if ($l['discountable']) $disc_idx[] = $l['index'];
        $targets = self::item_targets($rule, $lines);
        $item_idx = $targets ?? $disc_idx;
        $item_base = 0;
        foreach ($item_idx as $i) $item_base += $lines[$i]['line_total'];
        $item_base = $targets === null ? $base : (int) $item_base;

        $discount = 0;
        $w = [];
        $add = function (array $more) use (&$w) { foreach ($more as $i => $v) $w[$i] = ($w[$i] ?? 0) + $v; };
        foreach ($rule['actions'] as $n => $a) {
            if ($rule['stack_mode'] === 'best' && $n > 0) break;
            $v = $a['value'] ?? 0;
            switch ((string) ($a['type'] ?? '')) {
                case 'cart_discount_percent':
                    $d = (int) round($base * $v / 100); $discount += $d; $add(self::weights($lines, $disc_idx, $d)); break;
                case 'cart_discount_fixed':
                    $d = (int) $v; $discount += $d; $add(self::weights($lines, $disc_idx, $d)); break;
                case 'item_discount_percent':
                    $d = (int) round($item_base * $v / 100); $discount += $d; $add(self::weights($lines, $item_idx, $d)); break;
                case 'item_discount_fixed':
                    $d = (int) $v; $discount += $d; $add(self::weights($lines, $item_idx, $d)); break;
                case 'discount_cap':
                    $before = $discount;
                    $discount = min($discount, (int) $v);
                    if ($before > 0 && $discount < $before) foreach ($w as $i => $x) $w[$i] = $x * max(0, $discount) / $before;
                    break;
                case 'cost_cap': break;
                case 'block_purchase': $res['blocks'][] = $a['message'] ?? 'خرید شما با محدودیت مواجه شد.'; break;
                case 'free_shipping': $res['shipping_free'] = true; break;
                case 'item_surcharge_percent':
                case 'cart_surcharge_percent': $discount -= (int) round($subtotal * $v / 100); break;
                case 'item_surcharge_fixed':
                case 'cart_surcharge_fixed': $discount -= (int) $v; break;
            }
        }
        if ($discount === 0) return null;
        return [
            'rule_id'   => $rule['id'],
            'label'     => $rule['label'],
            'amount'    => $discount,
            'exclusive' => $rule['exclusive'],
            'group'     => $rule['group'],
            'campaign_id' => $rule['campaign_id'],
            'weights'   => $w,
        ];
    }

    /** Machine reason => the customer's sentence (the shops' own wording where they have one). */
    public static function code_reasons(): array {
        return [
            'not_found'        => 'کد تخفیف نامعتبر است.',
            'disabled'         => 'این کد تخفیف فعال نیست.',
            'channel'          => 'این کد تخفیف اینجا قابل استفاده نیست.',
            'not_started'      => 'این کد تخفیف هنوز فعال نشده است.',
            'expired'          => 'این کد تخفیف منقضی شده است.',
            'limit_reached'    => 'ظرفیت استفاده از این کد به پایان رسیده است.',
            'min_subtotal'     => 'حداقل مبلغ سفارش برای این کد رعایت نشده است.',
            'max_subtotal'     => 'این کد برای این مبلغ سفارش معتبر نیست.',
            'first_order_only' => 'این کد فقط برای اولین خرید است.',
            'customer_limit'   => 'سقف استفادهٔ شما از این کد تکمیل شده است.',
            'not_applicable'   => 'این کد روی کالاهای این سبد اعمال نمی‌شود.',
        ];
    }

    /** The discountable lines a code may touch: its include lists (any), minus its exclude lists. */
    public static function code_lines(array $code, array $lines): array {
        $out = [];
        $inc = $code['include_product_ids'] || $code['include_category_ids'];
        foreach ($lines as $l) {
            if (!$l['discountable']) continue;
            if (in_array($l['product_id'], $code['exclude_product_ids'], true)) continue;
            if ($code['exclude_category_ids'] && self::line_in_cats($l, $code['exclude_category_ids'])) continue;
            if ($inc && !in_array($l['product_id'], $code['include_product_ids'], true)
                && !($code['include_category_ids'] && self::line_in_cats($l, $code['include_category_ids']))) continue;
            $out[] = $l['index'];
        }
        return $out;
    }

    /**
     * Coupon::reasonInvalidFor (+ Cart::applyCoupon's «nothing here can take it»), in the
     * shops' order, with StoqS's channel and product lists. Null = valid.
     * $cust: null for a guest; else may carry paid_orders and code_uses (this code's uses).
     */
    public static function code_reason(array $code, int $subtotal, ?array $cust, ?string $channel, string $now, ?array $lines = null): ?string {
        if (!$code['enabled']) return 'disabled';
        if (!self::channel_ok($code['channels'], $channel)) return 'channel';
        if ($code['starts_at'] !== null && $now < $code['starts_at']) return 'not_started';
        if ($code['ends_at'] !== null && $now > $code['ends_at']) return 'expired';
        if ($code['usage_limit'] !== null && $code['uses'] >= $code['usage_limit']) return 'limit_reached';
        if ($subtotal < $code['min_subtotal']) return 'min_subtotal';
        if ($code['max_subtotal'] !== null && $subtotal > $code['max_subtotal']) return 'max_subtotal';
        if ($cust !== null) {
            if ($code['first_order_only'] && (int) ($cust['paid_orders'] ?? 0) > 0) return 'first_order_only';
            if ($code['per_customer_limit'] !== null && (int) ($cust['code_uses'] ?? 0) >= $code['per_customer_limit']) return 'customer_limit';
        }
        if ($lines !== null && $code['type'] !== 'free_shipping') {
            $base = 0;
            foreach (self::code_lines($code, $lines) as $i) $base += $lines[$i]['line_total'];
            if ($base <= 0) return 'not_applicable';
        }
        return null;
    }

    /** Coupon::discountFor on the code's own base. */
    public static function code_amount(array $code, int $base): int {
        if ($code['type'] === 'free_shipping') return 0;
        $d = $code['type'] === 'percent' ? (int) round($base * $code['value'] / 100) : (int) $code['value'];
        if ($code['max_discount'] !== null && $code['max_discount'] > 0) $d = min($d, (int) $code['max_discount']);
        return max(0, min($d, $base));
    }

    /** Schema 2: what a line's price already has off its list price, (L − S)·q, whole toman. */
    public static function line_pre(array $l): int {
        if ($l['list_price'] === null) return 0;
        return max(0, (int) round(($l['list_price'] - $l['unit_price']) * $l['qty']));
    }

    /**
     * $total whole toman spread over $weights [key => weight ≥ 0] in proportion, floor then
     * the largest remainders (ties: the earlier key), so the parts add up to $total exactly.
     */
    public static function int_spread(int $total, array $weights): array {
        $sum = array_sum($weights);
        $out = []; $fr = [];
        foreach ($weights as $k => $w) {
            $x = $sum > 0 ? $total * $w / $sum : 0.0;
            $out[$k] = (int) floor(round($x, 6));
            $fr[$k] = $x - $out[$k];
        }
        $left = $sum > 0 ? $total - array_sum($out) : 0;
        $keys = array_keys($fr);
        usort($keys, fn($a, $b) => [$fr[$b], array_search($a, array_keys($weights), true)] <=> [$fr[$a], array_search($b, array_keys($weights), true)]);
        foreach ($keys as $k) { if ($left <= 0) break; $out[$k]++; $left--; }
        return $out;
    }

    /**
     * Schema 2, an «alone» code measured against the list price, PER LINE, so splitting a
     * cart never changes what the code gives:
     *   · line i's promise P_i: round(L_i·q·c) for a percent code; a toman code spread over
     *     the code's lines by L_i·q (largest remainder, Σ P_i = value);
     *   · with max_discount, the promises are scaled down so Σ P_i ≤ max_discount (same
     *     proportional, largest-remainder spread);
     *   · the code adds Σ max(0, P_i − pre_i), pre_i = (L_i − S_i)·q: only what it gives
     *     beyond the campaign price on each line.
     * Each line's room (its floor and the list cap) still applies afterwards, in de_evaluate.
     *
     * @return array{0:int, 1:array<int,float>} [amount, weights per line index]
     */
    public static function code_amount_vs_list(array $code, array $L, array $idx): array {
        if ($code['type'] === 'free_shipping' || !$idx) return [0, []];
        $listq = []; $P = []; $base = 0;
        foreach ($idx as $i) {
            $l = $L[$i];
            $listq[$i] = ($l['list_price'] ?? $l['unit_price']) * $l['qty'];
            $base += (int) $l['line_total'];
            if ($code['type'] === 'percent') $P[$i] = (int) round(round($listq[$i] * $code['value'] / 100, 6));
        }
        if ($code['type'] !== 'percent') $P = self::int_spread(max(0, (int) $code['value']), $listq);
        if ($code['max_discount'] !== null && $code['max_discount'] > 0 && array_sum($P) > (int) $code['max_discount']) {
            $P = self::int_spread((int) $code['max_discount'], $P);
        }
        $extra = [];
        foreach ($idx as $i) $extra[$i] = max(0, $P[$i] - self::line_pre($L[$i]));
        $amount = max(0, min((int) array_sum($extra), $base));
        if ($amount <= 0) return [0, []];
        $sum = array_sum($extra);
        $w = [];
        foreach ($extra as $i => $e) if ($e > 0) $w[$i] = $e * $amount / $sum;
        return [$amount, $w];
    }

    /**
     * Evaluate a cart.
     *
     * $rules  rule rows / arrays (de_rule_normalize shape), all of the brand's; inactive ones are skipped here
     * $codes  the codes the customer entered, as rows (each may carry 'customer_uses')
     * $lines  [{variant_id, product_id, category_ids, qty, unit_price, …}] (de_line_normalize)
     * $opts   channel, customer (null = guest; phone, verified, group, previous_order_count,
     *         previous_order_total, city, paid_orders), context (payment_method_key,
     *         shipping_method_id, shipping_city, device), max_discount_percent (default 100,
     *         the shops' own fallback), min_margin_percent (for lines that carry a cost),
     *         has_costs (the shop has any cost at all; default: a line has a floor), now.
     *
     * Returns totals, the rules and codes with what each gave and whether it counted, and
     * lines[] with each line's discount split per source. Σ lines[].discount === discount.
     */
    public static function evaluate(array $rules, array $codes, array $lines, array $opts = []): array {
        $now     = (string) ($opts['now'] ?? date('Y-m-d H:i:s'));
        $channel = isset($opts['channel']) && $opts['channel'] !== '' ? (string) $opts['channel'] : null;
        $cust    = $opts['customer'] ?? null;
        $ctx     = (array) ($opts['context'] ?? []);
        $max     = (float) ($opts['max_discount_percent'] ?? 100);
        $minm    = (float) ($opts['min_margin_percent'] ?? 10);
        $L = [];
        foreach (array_values($lines) as $i => $l) $L[$i] = self::line_normalize($l, $i, $minm);
        $subtotal = 0; $base = 0;
        foreach ($L as $l) { $subtotal += $l['line_total']; if ($l['discountable']) $base += $l['line_total']; }
        $subtotal = (int) $subtotal; $base = (int) $base;

        $res = ['discounts' => [], 'blocks' => [], 'shipping_free' => false];

        // ── the codes (Cart::coupon): the valid ones, with their amount on their own base ──
        $C = [];
        foreach ($codes as $raw) {
            $c = self::code_normalize($raw);
            $cc = $cust;
            if ($cc !== null) $cc['code_uses'] = (int) ($raw['customer_uses'] ?? ($cust['code_uses'] ?? 0));
            $reason = $raw['_reason'] ?? self::code_reason($c, $subtotal, $cc, $channel, $now, $L);
            $idx = self::code_lines($c, $L);
            $cbase = 0; foreach ($idx as $i) $cbase += $L[$i]['line_total'];
            // schema 2: an «alone» code against the list price, only when its lines carry one
            $vs = $c['vs_list'] && $c['individual_use'] && $c['type'] !== 'free_shipping'
                && (bool) array_filter($idx, fn($i) => $L[$i]['list_price'] !== null);
            if ($vs && $reason === null) {
                [$amount, $weights] = self::code_amount_vs_list($c, $L, $idx);
            } else {
                $amount = $reason === null ? self::code_amount($c, (int) $cbase) : 0;
                $weights = self::weights($L, $idx, $amount);
            }
            $C[] = ['code' => $c, 'reason' => $reason, 'amount' => $amount, 'idx' => $idx,
                    'weights' => $weights, 'vs' => $vs];
        }
        $coupon = null;
        foreach ($C as $x) if ($x['reason'] === null) { $coupon = $x['code']; break; }

        // ── the rules (DiscountRuleEngine::evaluate) ──
        $R = array_map([self::class, 'rule_normalize'], $rules);
        usort($R, fn($a, $b) => [$b['priority'], $a['id']] <=> [$a['priority'], $b['id']]);
        foreach ($R as $rule) {
            if (!self::rule_active($rule, $now) || !self::channel_ok($rule['channels'], $channel)) continue;
            if ($rule['max_uses'] && $rule['uses'] >= $rule['max_uses']) continue;
            if (!$rule['conditions']) { if ($d = self::apply_actions($rule, $L, $subtotal, $base, $res)) $res['discounts'][] = $d; continue; }
            $all = $rule['apply_mode'] === 'all';
            $pass = $all;
            foreach ($rule['conditions'] as $cond) {
                $m = self::condition($cond, $L, $subtotal, $cust, $ctx, $coupon);
                if ($all && !$m) { $pass = false; break; }
                if (!$all && $m) { $pass = true; break; }
            }
            if ($pass && ($d = self::apply_actions($rule, $L, $subtotal, $base, $res))) $res['discounts'][] = $d;
        }

        // ── the cap (Cart::discountCap) and each line's room under it ──
        $has_costs = $opts['has_costs'] ?? null;
        if ($has_costs === null) { $has_costs = false; foreach ($L as $l) if ($l['floor_price'] !== null) { $has_costs = true; break; } }
        $list_mode = false;   // schema 2: a discountable line carries its list price
        foreach ($L as $l) if ($l['discountable'] && $l['list_price'] !== null) { $list_mode = true; break; }
        $room = [];
        // schema 3: a cap-exempt line's room is its whole total (no % cap, no floor); its total
        // is added whole to the cart's cap instead of its percent. Only for what is aimed at it:
        // $sroom keeps its normal room, the most another line's spill may put on it
        $sroom = [];
        $exempt = 0;
        foreach ($L as $l) if ($l['discountable'] && $l['cap_exempt']) $exempt += (int) $l['line_total'];
        if (!$list_mode) {
            $percent_cap = (int) floor(($base - $exempt) * $max / 100) + $exempt;
            foreach ($L as $i => $l) {
                if (!$l['discountable']) { $room[$i] = 0.0; continue; }
                $line = (int) $l['line_total'];
                $room[$i] = $has_costs
                    ? (float) min((int) floor($line * $max / 100), $l['floor_price'] === null ? $line : max(0, $line - (int) $l['floor_price'] * $l['qty']))
                    : $line * $max / 100;
                if ($l['cap_exempt']) { $sroom[$i] = (float) $room[$i]; $room[$i] = (float) $line; }
            }
        } else {
            $plain = 0; $percent_cap = 0;
            foreach ($L as $i => $l) {
                if (!$l['discountable']) { $room[$i] = 0.0; continue; }
                $line = (int) $l['line_total'];
                $by_floor = $l['floor_price'] === null ? $line : max(0, $line - (int) $l['floor_price'] * $l['qty']);
                if ($l['cap_exempt']) {
                    $sroom[$i] = (float) ($l['list_price'] === null
                        ? ($has_costs ? min((int) floor($line * $max / 100), $by_floor) : $line * $max / 100)
                        : ($has_costs ? min(max(0, (int) floor(round($l['list_price'] * $l['qty'] * $max / 100, 6)) - self::line_pre($l)), $by_floor)
                                      : max(0, (int) floor(round($l['list_price'] * $l['qty'] * $max / 100, 6)) - self::line_pre($l))));
                    $room[$i] = (float) $line; $percent_cap += $line; continue;
                }
                if ($l['list_price'] === null) {   // a line without a list price: today's room
                    $plain += $line;
                    $room[$i] = $has_costs ? (float) min((int) floor($line * $max / 100), $by_floor) : $line * $max / 100;
                    continue;
                }
                // the cap on the list total, less what the price already has off
                $lcap = max(0, (int) floor(round($l['list_price'] * $l['qty'] * $max / 100, 6)) - self::line_pre($l));
                $percent_cap += $lcap;
                $room[$i] = (float) ($has_costs ? min($lcap, $by_floor) : $lcap);
            }
            $percent_cap += (int) floor($plain * $max / 100);
        }
        $cap = $has_costs ? (int) min($percent_cap, array_sum($room)) : $percent_cap;

        // ── «use alone» (Cart::discountChoice) ──
        $positive = array_values(array_filter($res['discounts'], fn($d) => $d['amount'] > 0));
        $shared = array_values(array_filter($positive, fn($d) => !$d['exclusive']));
        $valid = array_values(array_filter(array_keys($C), fn($k) => $C[$k]['reason'] === null));
        // an «alone» code that gives something stands on its own; a vs_list one that adds
        // nothing beyond the campaign price stands alone too (and loses), so it is not
        // reported as applied
        $alone_code = fn($k) => $C[$k]['code']['individual_use'] && ($C[$k]['amount'] > 0 || $C[$k]['vs']);
        $shared_codes = array_values(array_filter($valid, fn($k) => !$alone_code($k)));
        $options = [[
            'codes'  => $shared_codes,
            'rules'  => array_column($shared, 'rule_id'),
            'amount' => array_sum(array_map(fn($k) => $C[$k]['amount'], $shared_codes)) + array_sum(array_column($shared, 'amount')),
            'alone'  => null,
        ]];
        $groups = [];
        foreach ($positive as $d) if ($d['exclusive']) $groups[$d['group'] ?? ('rule:' . $d['rule_id'])][] = $d;
        foreach ($groups as $g) {
            $options[] = ['codes' => [], 'rules' => array_column($g, 'rule_id'), 'amount' => array_sum(array_column($g, 'amount')),
                          'alone' => '«' . preg_replace('/ · .*$/u', '', (string) $g[0]['label']) . '»'];
        }
        foreach ($valid as $k) {
            if ($C[$k]['code']['individual_use'] && $C[$k]['amount'] > 0) {
                $options[] = ['codes' => [$k], 'rules' => [], 'amount' => $C[$k]['amount'], 'alone' => 'کد «' . $C[$k]['code']['code'] . '»'];
            }
        }
        // the sources an option gives (Cart::combinedDiscount): its codes, then its rules
        $sources_of = function (array $o) use ($C, $positive): array {
            $s = [];
            foreach ($o['codes'] as $k) if ($C[$k]['amount'] > 0) {
                $s[] = ['kind' => 'code', 'id' => $C[$k]['code']['id'], 'code' => $C[$k]['code']['code'],
                        'label' => $C[$k]['code']['label'], 'amount' => $C[$k]['amount'], 'weights' => $C[$k]['weights'], 'campaign_id' => null];
            }
            foreach ($positive as $d) if (in_array($d['rule_id'], $o['rules'], true)) {
                $s[] = ['kind' => 'rule', 'id' => $d['rule_id'], 'code' => null, 'label' => $d['label'],
                        'amount' => $d['amount'], 'weights' => $d['weights'], 'campaign_id' => $d['campaign_id']];
            }
            return $s;
        };
        // each option by what it really places. Beside a cap-exempt line an option can claim
        // more than sits (the exempt line takes only what is aimed at it, the cap counts its
        // whole total), so a dry allocation decides there; elsewhere that is min(amount, cap)
        $placed = function (array $o) use ($sources_of, $L, $room, $cap, $sroom) {
            if (!$sroom) return min($o['amount'], $cap);
            $src = $sources_of($o);
            $t = (int) min(array_sum(array_column($src, 'amount')), $cap);
            return (int) array_sum(array_column(self::allocate($L, $src, $room, $t, $sroom), 'amount'));
        };
        $best = $options[0];
        $best_placed = $placed($best);
        foreach ($options as $o) { $p = $placed($o); if ($p > $best_placed) { $best = $o; $best_placed = $p; } }

        $dropped = [];
        foreach ($positive as $d) if (!in_array($d['rule_id'], $best['rules'], true)) $dropped[] = '«' . $d['label'] . '»';
        foreach (array_reverse($valid) as $k) {
            if (($C[$k]['amount'] > 0 || $C[$k]['vs']) && !in_array($k, $best['codes'], true)) array_unshift($dropped, 'کد «' . $C[$k]['code']['code'] . '»');
        }
        $notice = null;
        if ($dropped) {
            $names = implode('، ', array_slice($dropped, 0, 3));
            $notice = $best['alone']
                ? $best['alone'] . ' با تخفیف‌های دیگر جمع نمی‌شود؛ چون بیشتر است، فقط همین حساب شد و ' . $names . ' حساب نشد.'
                : $names . ' با تخفیف‌های دیگر جمع نمی‌شود؛ تخفیف‌های دیگر سبد بیشتر بود و همان‌ها حساب شد.';
        }
        // an «alone» code that adds nothing beyond the campaign price, and nothing else counted
        if (!$best['rules'] && !array_filter($best['codes'], fn($k) => $C[$k]['amount'] > 0)
            && array_filter($valid, fn($k) => $C[$k]['vs'] && $C[$k]['amount'] <= 0)) {
            $notice = 'قیمت کمپین از این کد بهتر است.';
        }

        // ── the total (Cart::combinedDiscount) ──
        $sources = $sources_of($best);
        $want = array_sum(array_column($sources, 'amount'));
        $total = (int) min($want, $cap);
        $surcharge = 0;
        foreach ($res['discounts'] as $d) if ($d['amount'] < 0) $surcharge += -$d['amount'];

        // ── where the total sits: each line up to its room, the rest on lines with room left ──
        $alloc = self::allocate($L, $sources, $room, $total, $sroom);
        // an exempt line's own room cannot take another line's spill: the total is what sat
        if ($sroom) $total = (int) array_sum(array_column($alloc, 'amount'));

        $out_lines = [];
        foreach ($L as $i => $l) {
            $disc = $alloc[$i]['amount'];
            $net = $l['line_total'] - $disc;
            $unit_net = $l['qty'] > 0 ? $net / $l['qty'] : 0.0;
            $out_lines[] = [
                'index' => $i, 'variant_id' => $l['variant_id'], 'product_id' => $l['product_id'], 'qty' => $l['qty'],
                'unit_price' => $l['unit_price'], 'line_total' => $l['line_total'], 'discount' => $disc,
                'room' => $l['discountable'] ? (int) floor($room[$i] + 1e-9) : 0, 'floor_price' => $l['floor_price'],
                'unit_net' => round($unit_net, 2),
                'margin_ok' => $l['floor_price'] === null || $unit_net + 0.005 >= $l['floor_price'],
                'parts' => $alloc[$i]['parts'],
            ];
            if ($list_mode && $l['list_price'] !== null) $out_lines[count($out_lines) - 1]['list_price'] = $l['list_price'];
        }
        $code_out = [];
        foreach ($C as $k => $x) {
            $code_out[] = ['id' => $x['code']['id'], 'code' => $x['code']['code'], 'valid' => $x['reason'] === null,
                           'reason' => $x['reason'], 'message' => $x['reason'] !== null ? (self::code_reasons()[$x['reason']] ?? null) : null,
                           'amount' => $x['amount'], 'applied' => in_array($k, $best['codes'], true) || ($x['reason'] === null && $x['code']['type'] === 'free_shipping'),
                           'free_shipping' => $x['reason'] === null && $x['code']['type'] === 'free_shipping'];
        }
        $shipping_free = $res['shipping_free'];
        foreach ($code_out as $c) if ($c['free_shipping']) $shipping_free = true;
        $out = [
            'subtotal'              => $subtotal,
            'discountable_subtotal' => $base,
            'cap'                   => $cap,
            'discount'              => $total,
            'surcharge'             => $surcharge,
            'total'                 => max(0, $subtotal - $total + $surcharge),
            'shipping_free'         => $shipping_free,
            'blocks'                => $res['blocks'],
            'rules'                 => array_map(fn($d) => [
                'rule_id' => $d['rule_id'], 'label' => $d['label'], 'amount' => $d['amount'], 'exclusive' => $d['exclusive'],
                'group' => $d['group'], 'applied' => $d['amount'] < 0 || in_array($d['rule_id'], $best['rules'], true),
            ], $res['discounts']),
            'codes'                 => $code_out,
            'dropped'               => $dropped,
            'alone'                 => $best['alone'],
            'notice'                => $notice,
            'lines'                 => $out_lines,
        ];
        if ($list_mode) $out['cap_basis'] = 'list';   // schema 2 only: the shape is today's otherwise
        return $out;
    }

    /**
     * Split $total over the lines. Each source's amount sits where its actions put it
     * (weights); a line takes at most its room; what a full line cannot take goes to the
     * lines with room left (first those the discount was aimed at, then any discountable
     * line), so the lines always add up to exactly what the shops charge. A cap-exempt line
     * ($sroom: its normal room) takes spill only up to that; what fits nowhere is not given. Whole toman,
     * largest remainder, never over a line's room.
     *
     * @return array<int, array{amount:int, parts:list<array>}>
     */
    public static function allocate(array $L, array $sources, array $room, int $total, array $sroom = []): array {
        $n = count($L);
        $out = [];
        for ($i = 0; $i < $n; $i++) $out[$i] = ['amount' => 0, 'parts' => []];
        if ($total <= 0 || !$sources) return $out;

        // demand per line and per (line, source), each source scaled to its own amount
        $dem = array_fill(0, $n, 0.0);
        $by = [];
        foreach ($sources as $s => $src) {
            $w = $src['weights'];
            $sum = array_sum($w);
            if ($sum <= 0) {   // nowhere to sit (a fixed amount on no line): spread on the discountable lines
                $w = []; foreach ($L as $l) if ($l['discountable']) $w[$l['index']] = $l['line_total'];
                $sum = array_sum($w);
            }
            if ($sum <= 0) continue;
            foreach ($w as $i => $x) { $v = $src['amount'] * $x / $sum; $by[$i][$s] = ($by[$i][$s] ?? 0) + $v; $dem[$i] += $v; }
        }
        // each line up to its room, then scale down if the cap is below the demand
        $a = [];
        for ($i = 0; $i < $n; $i++) $a[$i] = min($dem[$i], $room[$i]);
        $sa = array_sum($a);
        if ($sa > $total && $sa > 0) foreach ($a as $i => $v) $a[$i] = $v * $total / $sa;
        // the rest onto lines with room left: aimed-at lines first, then any discountable line
        $spill = array_fill(0, $n, 0.0);
        foreach ([true, false] as $aimed_only) {
            for ($round = 0; $round < 8; $round++) {
                $rest = $total - array_sum($a);
                if ($rest <= 1e-6) break 2;
                $free = [];
                for ($i = 0; $i < $n; $i++) {
                    if ($aimed_only && $dem[$i] <= 0) continue;
                    $f = (isset($sroom[$i]) ? min($room[$i], $sroom[$i]) : $room[$i]) - $a[$i];
                    if ($f > 1e-9) $free[$i] = $f;
                }
                $fs = array_sum($free);
                if ($fs <= 1e-9) break;
                foreach ($free as $i => $f) { $add = min($f, $rest * $f / $fs); $a[$i] += $add; $spill[$i] += $add; }
            }
        }
        // what could sit nowhere (only ever beside a cap-exempt line) is not given
        if ($sroom && $total - array_sum($a) > 0.5) $total = (int) floor(array_sum($a) + 1e-6);
        // whole toman per line: floor, then the largest remainders (never past the room)
        $int = []; $fr = [];
        for ($i = 0; $i < $n; $i++) { $int[$i] = (int) floor($a[$i] + 1e-9); $fr[$i] = $a[$i] - $int[$i]; }
        $left = $total - array_sum($int);
        arsort($fr);
        foreach (array_keys($fr) as $i) {
            if ($left <= 0) break;
            if ($int[$i] + 1 <= $room[$i] + 0.999) { $int[$i]++; $left--; }
        }
        if ($left > 0) foreach (array_keys($fr) as $i) { if ($left <= 0) break; if ($L[$i]['discountable']) { $int[$i]++; $left--; } }

        // each line's amount per source: its own demand first, the spill by the sources' shares
        $share = [];
        $want = array_sum(array_column($sources, 'amount'));
        foreach ($sources as $s => $src) $share[$s] = $want > 0 ? $src['amount'] / $want : 0;
        for ($i = 0; $i < $n; $i++) {
            if ($int[$i] <= 0) continue;
            $own = $a[$i] - $spill[$i];
            $raw = [];
            foreach ($sources as $s => $src) {
                $mine = ($dem[$i] > 0 ? ($by[$i][$s] ?? 0) / $dem[$i] * $own : 0) + $spill[$i] * $share[$s];
                if ($mine > 0) $raw[$s] = $mine;
            }
            $rs = array_sum($raw);
            if ($rs <= 0) continue;
            $pi = []; $pf = [];
            foreach ($raw as $s => $v) { $x = $v / $rs * $int[$i]; $pi[$s] = (int) floor($x + 1e-9); $pf[$s] = $x - $pi[$s]; }
            $l2 = $int[$i] - array_sum($pi);
            arsort($pf);
            foreach (array_keys($pf) as $s) { if ($l2 <= 0) break; $pi[$s]++; $l2--; }
            $parts = [];
            foreach ($pi as $s => $v) {
                if ($v <= 0) continue;
                $src = $sources[$s];
                $parts[] = ['type' => $src['kind'], 'id' => $src['id'], 'code' => $src['code'], 'label' => $src['label'],
                            'campaign_id' => $src['campaign_id'], 'amount' => $v];
            }
            $out[$i] = ['amount' => $int[$i], 'parts' => $parts];
        }
        return $out;
    }
}
