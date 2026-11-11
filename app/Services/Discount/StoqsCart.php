<?php

namespace App\Services\Discount;

use App\Models\Coupon;
use App\Models\User;
use App\Services\StockKeeping\StoqsApi;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The cart, priced by StoqS (only while StoqS manages this site's promotions).
 *
 * evaluate() runs StoqS's engine (StoqsEngine, a port of StoqS's own) on the /promotions
 * payload: its rules and codes, its no-discount categories, its cap. That is what the cart
 * page and the checkout page show. remote() asks POST /promotions/evaluate, StoqS's
 * official answer, at order placement (Cart::promotionsCheck).
 *
 * Both return the engine's result shape (de_evaluate) plus `meta`: per engine line, the
 * local variant id, the list price and the window that prices it now. Only sizes StoqS
 * knows (a StoqS variant id, not a bundle) are cart lines for StoqS; anything else takes
 * no discount.
 */
class StoqsCart
{
    /**
     * @param  Collection<int, array{variant: \App\Models\ProductVariant, quantity: int, unit_price: int, line_total: int}>  $lines
     * @return array{0: array<int, array>, 1: array<int, array>} [engine lines, meta]
     */
    public static function lines(Collection $lines): array
    {
        $index = StoqsPromotions::index();
        $cats = StoqsPromotions::categoriesOf();
        $none = array_map('intval', (array) (StoqsPromotions::settings()['no_discount_category_ids'] ?? []));
        $ts = now()->getTimestamp();
        $eng = [];
        $meta = [];
        foreach ($lines as $l) {
            $v = $l['variant'];
            $p = $v->product;
            if (! $v->stockkeeping_variant_id || ! $p || $p->is_bundle) {
                continue;
            }
            $svid = (int) $v->stockkeeping_variant_id;
            $row = $index[$svid] ?? null;
            $spid = (int) ($row['product_id'] ?? self::stoqsProduct($p, $svid));
            [$list, , $win] = StoqsPromotions::sizeNow($v, null, $ts);
            $unit = (int) $l['unit_price'];
            $list = max($list, $unit);
            $cids = $cats[$spid] ?? [];
            $floor = $row !== null && array_key_exists('floor_price', $row) ? $row['floor_price']
                : ($p->stoqs_floor_price ?? null);
            $eng[] = [
                'variant_id' => $svid, 'product_id' => $spid, 'category_ids' => $cids,
                'qty' => (int) $l['quantity'], 'unit_price' => $unit, 'list_price' => $list,
                'floor_price' => $floor === null ? null : (float) $floor,
                'discountable' => ! array_intersect($cids, $none), 'on_sale' => $unit < $list,
            ];
            $meta[] = ['local_id' => (int) $v->id, 'list' => $list, 'unit' => $unit, 'qty' => (int) $l['quantity'],
                'window' => $win === null ? null : array_intersect_key($win, array_flip(['id', 'source', 'campaign_id', 'plan_id', 'version']))];
        }

        return [$eng, $meta];
    }

    /** The StoqS product a size belongs to (colours merged into one shop product have several). */
    private static function stoqsProduct($product, int $svid): int
    {
        foreach ((array) ($product->stoqs_meta['p'] ?? []) as $spid => $row) {
            if (in_array($svid, array_map('intval', (array) ($row['v'] ?? [])), true)) {
                return (int) $spid;
            }
        }

        return (int) $product->stockkeeping_id;
    }

    /** The payload's rules / codes in the engine's shape (dates as Tehran wall-clock time, as StoqS compares them). */
    public static function rules(): array
    {
        return array_map(fn ($r) => self::tehranDates((array) $r), array_values(array_filter(
            (array) (StoqsPromotions::payload()['rules'] ?? []), fn ($r) => is_array($r) && empty($r['deleted']))));
    }

    /** The payload code a customer typed (any case, Persian digits too), or null. */
    public static function findCode(string $text): ?array
    {
        $want = StoqsEngine::code_norm($text);
        foreach ((array) (StoqsPromotions::payload()['codes'] ?? []) as $c) {
            if (is_array($c) && empty($c['deleted']) && StoqsEngine::code_norm((string) ($c['code'] ?? '')) === $want) {
                return self::tehranDates($c);
            }
        }

        return null;
    }

    private static function tehranDates(array $row): array
    {
        foreach (['starts_at', 'ends_at'] as $k) {
            if (! empty($row[$k])) {
                try {
                    $row[$k] = Carbon::parse((string) $row[$k])->setTimezone('Asia/Tehran')->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    $row[$k] = null;
                }
            }
        }

        return $row;
    }

    /**
     * The local evaluation: StoqS's engine on the payload. $codes: what the customer typed.
     * The customer is a guest to the engine, as in StoqS's own evaluate.
     */
    public static function evaluate(Collection $lines, array $codes = [], array $context = []): array
    {
        [$eng, $meta] = self::lines($lines);
        $rows = [];
        foreach ($codes as $text) {
            $rows[] = self::findCode((string) $text) ?? ['code' => (string) $text, '_reason' => 'not_found', 'enabled' => false];
        }
        $ev = StoqsEngine::evaluate(self::rules(), $rows, $eng, [
            'channel' => 'web', 'customer' => null, 'now' => now('Asia/Tehran')->format('Y-m-d H:i:s'),
            'max_discount_percent' => (float) (StoqsPromotions::settings()['max_discount_percent'] ?? 100),
            'context' => array_intersect_key($context, array_flip(['payment_method_key', 'shipping_method_id', 'shipping_city'])),
        ]);
        $ev['meta'] = $meta;
        $ev['eval'] = 'local';
        $ev['version'] = StoqsPromotions::version();

        return $ev;
    }

    /**
     * POST /promotions/evaluate: StoqS's official discounts for this cart, in the same shape
     * as evaluate() (its lines in our order). Null when StoqS cannot be reached or refuses;
     * the caller then sells on the local evaluation (eval: local).
     */
    public static function remote(Collection $lines, array $codes = [], ?User $user = null): ?array
    {
        [$eng, $meta] = self::lines($lines);
        if (! $eng) {
            return null;
        }
        $body = [
            'lines' => array_map(fn ($l, $m) => array_filter([
                'variant_id' => $l['variant_id'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'list_price' => $l['list_price'],
                'plan_id' => $m['window']['plan_id'] ?? null, 'plan_version' => $m['window']['version'] ?? null,
            ], fn ($x) => $x !== null), $eng, $meta),
            'codes' => array_values(array_map('strval', $codes)),
            'site_url' => StoqsPromotions::siteUrl(),
        ];
        if ($user?->phone) {
            $body['customer_phone'] = (string) $user->phone;
        }
        try {
            $resp = StoqsApi::client()->timeout(8)->post('promotions/evaluate', $body);
        } catch (\Throwable $e) {
            Log::warning('[stoqs-promotions] evaluate unreachable', ['error' => $e->getMessage()]);

            return null;
        }
        $j = $resp->successful() ? (array) $resp->json() : [];
        if (empty($j['ok']) || ! is_array($j['lines'] ?? null) || count($j['lines']) !== count($eng)) {
            Log::warning('[stoqs-promotions] evaluate refused', ['status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 300)]);

            return null;
        }
        $out = [];
        foreach ($j['lines'] as $l) {
            $out[(int) ($l['index'] ?? count($out))] = [
                'index' => (int) ($l['index'] ?? count($out)), 'variant_id' => (int) ($l['variant_id'] ?? 0), 'qty' => (int) ($l['qty'] ?? 0),
                'discount' => (int) ($l['discount'] ?? 0), 'parts' => (array) ($l['parts'] ?? []),
                'room' => null, 'floor_price' => $l['floor_price'] ?? null, 'margin_ok' => (bool) ($l['margin_ok'] ?? true),
                'stoqs' => array_intersect_key($l, array_flip(['list_price', 'price', 'price_source', 'campaign_id', 'plan_id', 'plan_version'])),
            ];
        }
        ksort($out);
        foreach ($meta as $i => $m) {
            $s = $out[$i]['stoqs'] ?? [];
            if (! empty($s['plan_id']) || ! empty($s['campaign_id'])) {
                $meta[$i]['window'] = ['id' => null, 'source' => $s['price_source'] ?? null, 'campaign_id' => $s['campaign_id'] ?? null,
                    'plan_id' => $s['plan_id'] ?? null, 'version' => $s['plan_version'] ?? null];
            }
        }

        return [
            'subtotal' => (int) ($j['subtotal'] ?? 0), 'discount' => (int) ($j['discount'] ?? 0), 'cap' => (int) ($j['cap'] ?? 0),
            'surcharge' => (int) ($j['surcharge'] ?? 0), 'total' => (int) ($j['total'] ?? 0),
            'shipping_free' => (bool) ($j['shipping_free'] ?? false), 'blocks' => (array) ($j['blocks'] ?? []),
            'rules' => (array) ($j['rules'] ?? []), 'codes' => (array) ($j['codes'] ?? []),
            'dropped' => (array) ($j['dropped'] ?? []), 'alone' => $j['alone'] ?? null, 'notice' => $j['notice'] ?? null,
            'lines' => array_values($out), 'meta' => $meta, 'mismatch' => (array) ($j['mismatch'] ?? []),
            'eval' => 'stoqs', 'version' => isset($j['version']) ? (int) $j['version'] : StoqsPromotions::version(),
        ];
    }

    /** The code that counts, as an unsaved Coupon (for the views and the order's coupon_code). Never saved. */
    public static function couponModel(array $code): Coupon
    {
        $type = (string) ($code['type'] ?? 'percent');

        return new Coupon([
            'code' => (string) ($code['code'] ?? ''), 'description' => $code['label'] ?? null,
            'type' => $type === 'toman' ? 'fixed' : ($type === 'free_shipping' ? 'free_shipping' : 'percent'),
            'value' => (int) ($code['value'] ?? 0), 'min_subtotal' => (int) ($code['min_subtotal'] ?? 0),
            'max_discount' => $code['max_discount'] ?? null, 'exclusive' => ! empty($code['individual_use']), 'is_active' => true,
        ]);
    }

    /**
     * What each line got, for the order and the StoqS sale report: the price layer (list −
     * price, `layer: price`, typed campaign or price_sheet, with its campaign id, plan id
     * and version), then each rule and code part with its StoqS id.
     *
     * @return array<int, array<int, array>> local variant id => discounts
     */
    public static function lineDiscounts(array $ev): array
    {
        $out = [];
        foreach ($ev['meta'] as $i => $m) {
            $id = $m['local_id'];
            $pre = max(0, ($m['list'] - $m['unit']) * $m['qty']);
            $w = $m['window'] ?? null;
            if ($pre > 0 && $w) {
                $src = (string) ($w['source'] ?? 'campaign');
                $out[$id][] = array_filter([
                    'type' => $src === 'price_sheet' ? 'price_sheet' : 'campaign',
                    'id' => $src === 'price_sheet' ? null : ($w['campaign_id'] ?? null),
                    'plan_id' => $w['plan_id'] ?? null, 'version' => $w['version'] ?? null,
                    'amount' => $pre, 'layer' => 'price',
                ], fn ($x) => $x !== null);
            }
            foreach ((array) ($ev['lines'][$i]['parts'] ?? []) as $part) {
                if ((int) ($part['amount'] ?? 0) <= 0) {
                    continue;
                }
                [$plan, $version] = self::planOf($part);
                $out[$id][] = array_filter([
                    'type' => ($part['type'] ?? '') === 'code' ? 'code' : 'rule',
                    'id' => isset($part['id']) ? (int) $part['id'] : null,
                    'code' => $part['code'] ?? null,
                    'plan_id' => $plan,
                    'version' => $version,
                    'amount' => (int) $part['amount'],
                ], fn ($x) => $x !== null);
            }
        }

        return $out;
    }

    /** [plan id, plan version] of a rule or code part a plan made (the payload says plan_id), else [null, null]. */
    private static function planOf(array $part): array
    {
        $p = StoqsPromotions::payload();
        $key = ($part['type'] ?? '') === 'code' ? 'codes' : 'rules';
        foreach ((array) ($p[$key] ?? []) as $row) {
            if ((int) ($row['id'] ?? 0) === (int) ($part['id'] ?? -1) && ! empty($row['plan_id'])) {
                $plan = (int) $row['plan_id'];
                foreach ((array) ($p['plans'] ?? []) as $pl) {
                    if ((int) ($pl['id'] ?? 0) === $plan) {
                        return [$plan, (int) ($pl['version'] ?? 0) ?: null];
                    }
                }

                return [$plan, null];
            }
        }

        return [null, null];
    }

    /** The StoqS list price per local variant id, for order_items.list_price. */
    public static function listPrices(array $ev): array
    {
        $out = [];
        foreach ($ev['meta'] as $m) {
            $out[$m['local_id']] = (int) $m['list'];
        }

        return $out;
    }

    /** The nearest single-condition rule the cart has not reached («n more»), from the payload's rules. */
    public static function nextOffer(array $ev, Collection $lines): ?array
    {
        [$eng] = self::lines($lines);
        $applied = array_column(array_filter($ev['rules'] ?? [], fn ($r) => ! empty($r['applied'])), 'rule_id');
        $best = null;
        foreach (self::rules() as $r) {
            $n = StoqsEngine::rule_normalize($r);
            if (count($n['conditions']) !== 1 || in_array($n['id'], $applied, true) || $n['label'] === ''
                || ! StoqsEngine::rule_active($n, now('Asia/Tehran')->format('Y-m-d H:i:s')) || ! StoqsEngine::channel_ok($n['channels'], 'web')) {
                continue;
            }
            $c = $n['conditions'][0];
            $min = (int) ($c['min'] ?? 0);
            $ids = array_map('intval', (array) ($c['category_ids'] ?? []));
            $have = match ($c['type'] ?? '') {
                'cart_item_count' => array_sum(array_column($eng, 'qty')),
                'cart_subtotal' => array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $eng)),
                'item_count_from_categories' => array_sum(array_map(fn ($l) => array_intersect($l['category_ids'], $ids) ? $l['qty'] : 0, $eng)),
                default => null,
            };
            if ($have === null || $have >= $min) {
                continue;
            }
            $items = $c['type'] !== 'cart_subtotal';
            $gap = $min - $have;
            if (($items && $gap > 3) || (! $items && $gap > $min / 2)) {
                continue;
            }
            if (! $best || ($items && ! $best['items']) || ($items === $best['items'] && $gap < $best['gap'])) {
                $best = ['items' => $items, 'gap' => $gap, 'label' => preg_replace('/ · .*$/u', '', $n['label'])];
            }
        }

        return $best;
    }
}
