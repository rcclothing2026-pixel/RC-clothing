<?php

namespace App\Services\Cart;

use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\SavedCart;
use App\Services\Discount\DiscountRuleEngine;
use App\Services\Discount\StoqsCart;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Cart
{
    private const KEY = 'cart';

    private const COUPON = 'cart_coupon';

    private const GIFT = 'cart_gift';

    /** StoqS-managed: StoqS's own answer adopted at checkout, for the cart it was given for. */
    private const STOQS_SEEN = 'cart_stoqs_seen';

    private ?array $ruleResultsCache = null;

    /** StoqS-managed promotions only: the engine's result for this cart (StoqsCart). */
    private ?array $stoqsCache = null;

    public function __construct(private readonly Session $session) {}

    /** @return array<int,int> variantId => qty */
    private function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    private function persist(array $items): void
    {
        $this->ruleResultsCache = null;
        $this->stoqsCache = null;
        $this->session->put(self::KEY, $items);
        $this->syncSaved($items);
    }

    private function syncSaved(array $items): void
    {
        $user = Auth::user();
        if (! $user || ! Schema::hasTable('saved_carts')) {
            return;
        }
        if ($items === []) {
            SavedCart::where('user_id', $user->id)->delete();

            return;
        }
        $cart = SavedCart::firstOrNew(['user_id' => $user->id]);
        if (! $cart->exists) {
            $cart->token = Str::random(40);
        }
        $cart->items = $items;
        $cart->reminded_at = null;
        $cart->save();
    }

    public function restoreItems(array $items): void
    {
        $this->persist($items);
    }

    public function add(int $variantId, int $qty = 1): void
    {
        $items = $this->raw();
        $items[$variantId] = ($items[$variantId] ?? 0) + max(1, $qty);
        $this->persist($items);
    }

    public function update(int $variantId, int $qty): void
    {
        $items = $this->raw();
        if ($qty <= 0) {
            unset($items[$variantId]);
        } else {
            $items[$variantId] = $qty;
        }
        $this->persist($items);
    }

    public function remove(int $variantId): void
    {
        $items = $this->raw();
        unset($items[$variantId]);
        $this->persist($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
        $this->session->forget(self::COUPON);
        $this->session->forget(self::GIFT);
        if (($user = Auth::user()) && Schema::hasTable('saved_carts')) {
            SavedCart::where('user_id', $user->id)->delete();
        }
    }

    /* ----------------------------- Coupons ----------------------------- */

    public function coupon(): ?Coupon
    {
        $code = $this->session->get(self::COUPON);
        if (! $code) {
            return null;
        }
        if (StoqsPromotions::managed()) {
            $c = $this->stoqs()['codes'][0] ?? null;

            return $c && ! empty($c['valid']) && ($row = StoqsCart::findCode((string) $code)) ? StoqsCart::couponModel($row) : null;
        }
        $coupon = Coupon::findByCode($code);
        if (! $coupon || ! $coupon->isValidFor($this->subtotal(), Auth::user())) {
            return null;
        }

        return $coupon;
    }

    /** @return array{ok: bool, message: string} */
    public function applyCoupon(string $code): array
    {
        if (StoqsPromotions::managed()) {
            return $this->applyStoqsCode($code);
        }
        $coupon = Coupon::findByCode($code);
        if (! $coupon) {
            return ['ok' => false, 'message' => 'کد تخفیف نامعتبر است.'];
        }
        if ($reason = $coupon->reasonInvalidFor($this->subtotal(), Auth::user())) {
            return ['ok' => false, 'message' => $reason];
        }
        if (! $coupon->isFreeShipping() && $this->discountableSubtotal() === 0) {
            return ['ok' => false, 'message' => 'این کد روی بسته‌ها، اقلام زمستانه و اوتلت اعمال نمی‌شود.'];
        }
        $this->session->put(self::COUPON, $coupon->code);

        return ['ok' => true, 'message' => 'کد تخفیف اعمال شد.'];
    }

    public function removeCoupon(): void
    {
        $this->session->forget(self::COUPON);
        $this->forgetStoqs();
    }

    /** StoqS-managed: a code is one of StoqS's codes for this site, checked by its engine. */
    private function applyStoqsCode(string $code): array
    {
        $row = StoqsCart::findCode($code);
        $ev = StoqsCart::evaluate($this->lines(), [$row['code'] ?? $code], $this->ruleContext, Auth::user());
        $c = $ev['codes'][0] ?? null;
        if (! $row || ! $c || empty($c['valid'])) {
            return ['ok' => false, 'message' => (string) ($c['message'] ?? 'کد تخفیف نامعتبر است.')];
        }
        $this->session->put(self::COUPON, (string) $row['code']);
        $this->forgetStoqs();

        return ['ok' => true, 'message' => 'کد تخفیف اعمال شد.'];
    }

    /**
     * StoqS-managed: this cart through StoqS's engine on the /promotions payload (or StoqS's
     * own answer after promotionsCheck()).
     */
    public function stoqs(): array
    {
        if ($this->stoqsCache === null) {
            $codes = $this->stoqsCodes();
            $lines = $this->lines();
            $seen = $this->session->get(self::STOQS_SEEN);
            $this->stoqsCache = is_array($seen) && ($seen['hash'] ?? null) === StoqsCart::fingerprint($lines, $codes)
                ? $seen['ev']
                : StoqsCart::evaluate($lines, $codes, $this->ruleContext, Auth::user());
        }

        return $this->stoqsCache;
    }

    /** @return string[] the code the customer entered (StoqS-managed carts) */
    private function stoqsCodes(): array
    {
        $code = $this->session->get(self::COUPON);

        return $code ? [(string) $code] : [];
    }

    private function forgetStoqs(): void
    {
        $this->stoqsCache = null;
        $this->ruleResultsCache = null;
    }

    /**
     * At order placement, when StoqS manages this site: ask StoqS (POST /promotions/evaluate)
     * and use its answer for the order.
     *
     * - StoqS unreachable or refusing: the order is sold on the local evaluation (what the
     *   customer saw), marked eval «local».
     * - StoqS prices a line differently (outside its grace): the payload is pulled again (once
     *   per version) and only this cart's products are re-priced from it; when a cart price
     *   changed the customer goes back to the cart.
     * - StoqS's answer costs the customer more than what the cart showed (less discount, a
     *   surcharge, shipping no longer free, a block, a code no longer valid): it is adopted
     *   (kept in the session for this very cart, so the cart shows it) and refresh is true with
     *   the reason, so the customer sees the amount before paying. A cheaper answer is used
     *   as it is.
     *
     * Null when StoqS does not manage this site (nothing changes then).
     *
     * @return array{eval: string, version: ?int, refresh: bool, message?: string}|null
     */
    public function promotionsCheck(): ?array
    {
        if (! StoqsPromotions::managed()) {
            return null;
        }
        $this->forgetStoqs();
        $seen = $this->stoqs();
        $codes = $this->stoqsCodes();
        $remote = StoqsCart::remote($this->lines(), $codes, Auth::user(), $this->ruleContext);
        if ($remote === null) {
            return ['eval' => 'local', 'version' => $seen['version'] ?? null, 'refresh' => false];
        }
        $off = array_filter($remote['mismatch'], fn ($m) => ($m['field'] ?? '') === 'unit_price' && empty($m['within_grace']));
        if ($off && StoqsPromotions::repriceCart($this->lines())) {
            $this->forgetStoqs();

            return ['eval' => 'stoqs', 'version' => $remote['version'], 'refresh' => true,
                'message' => 'قیمت بعضی کالاها همین حالا تغییر کرد؛ لطفاً سبد خرید را دوباره ببینید.'];
        }
        $worse = StoqsCart::worseFor($seen, $remote);
        $this->stoqsCache = $remote;
        $this->ruleResultsCache = null;
        if ($worse !== null) {
            // Keep StoqS's answer for this cart, without a code StoqS refused, and show it.
            $keep = array_values(array_diff($codes, $worse['codes']));
            if ($keep !== $codes) {
                $keep ? $this->session->put(self::COUPON, $keep[0]) : $this->session->forget(self::COUPON);
            }
            $this->session->put(self::STOQS_SEEN, ['hash' => StoqsCart::fingerprint($this->lines(), $keep), 'ev' => $remote]);

            return ['eval' => 'stoqs', 'version' => $remote['version'], 'refresh' => true, 'message' => $worse['message']];
        }

        return ['eval' => 'stoqs', 'version' => $remote['version'], 'refresh' => false];
    }

    /** StoqS-managed: the list price each line was priced from (order_items.list_price); [] otherwise. */
    public function listPrices(): array
    {
        return StoqsPromotions::managed() ? StoqsCart::listPrices($this->stoqs()) : [];
    }

    /* --------------------------- Discount Rules ------------------------- */

    private array $ruleContext = [];

    /**
     * Set checkout context for rule evaluation (payment method, shipping, city).
     *
     * @param  array{payment_method_key?: string, shipping_method_id?: int, shipping_city?: string}  $context
     */
    public function setRuleContext(array $context): void
    {
        $this->ruleContext = $context;
        $this->ruleResultsCache = null;
        $this->stoqsCache = null;
    }

    /**
     * Evaluate all active discount rules against the current cart.
     * Results are cached for the current request.
     *
     * @return array{discounts: array, blocks: array, shipping_free: bool, surcharges: array}
     */
    public function ruleResults(): array
    {
        if ($this->ruleResultsCache !== null) {
            return $this->ruleResultsCache;
        }

        if (StoqsPromotions::managed()) {   // StoqS's rules, not the shop's own
            $ev = $this->isEmpty() ? null : $this->stoqs();

            return $this->ruleResultsCache = $ev === null ? ['discounts' => [], 'blocks' => [], 'shipping_free' => false] : [
                'discounts' => array_map(fn ($r) => ['rule_id' => (int) $r['rule_id'], 'rule_name' => (string) ($r['label'] ?? ''),
                    'amount' => (int) $r['amount'], 'exclusive' => ! empty($r['exclusive']), 'group' => $r['group'] ?? null, 'stoqs' => true], (array) $ev['rules']),
                'blocks' => (array) ($ev['blocks'] ?? []),
                'shipping_free' => (bool) ($ev['shipping_free'] ?? false),
            ];
        }

        if ($this->isEmpty() || ! Schema::hasTable('discount_rules')) {
            $this->ruleResultsCache = ['discounts' => [], 'blocks' => [], 'shipping_free' => false];
            return $this->ruleResultsCache;
        }

        $this->ruleResultsCache = app(DiscountRuleEngine::class)->evaluate($this, Auth::user(), $this->ruleContext);

        return $this->ruleResultsCache;
    }

    /** Total discount from the rules that count (surcharges excluded). */
    public function ruleDiscount(): int
    {
        return (int) collect($this->ruleDiscountBreakdown())->sum(fn ($d) => max(0, (int) ($d['amount'] ?? 0)));
    }

    /** Total surcharge from rules. */
    public function ruleSurcharge(): int
    {
        $total = 0;
        foreach ($this->ruleResults()['discounts'] as $d) {
            $amount = (int) ($d['amount'] ?? 0);
            if ($amount < 0) {
                $total += abs($amount);
            }
        }
        return $total;
    }

    public function hasRuleBlocks(): bool
    {
        return ! empty($this->ruleResults()['blocks']);
    }

    /** @return string[] */
    public function ruleBlockMessages(): array
    {
        return $this->ruleResults()['blocks'];
    }

    /** Rule discounts that count (and every surcharge), for display and order records. */
    public function ruleDiscountBreakdown(): array
    {
        $keep = $this->discountChoice()['rules'];

        return array_values(array_filter($this->ruleResults()['discounts'],
            fn ($d) => (int) ($d['amount'] ?? 0) < 0 || in_array($d['rule_id'] ?? null, $keep, true)));
    }

    /**
     * «Use alone» resolution. Discounts add up as before, except an exclusive
     * coupon or rule never adds to anything else: when one meets others the
     * cart keeps whichever gives the customer more (after the overall cap).
     * Free-shipping coupons always stay; surcharges are never affected.
     *
     * @return array{coupon: bool, rules: array<int>, dropped: array<string>, alone: ?string}
     */
    public function discountChoice(): array
    {
        if (StoqsPromotions::managed()) {
            $ev = $this->stoqs();

            return [
                'coupon' => (bool) array_filter((array) $ev['codes'], fn ($c) => ! empty($c['applied']) && (int) ($c['amount'] ?? 0) > 0),
                'rules' => array_values(array_map(fn ($r) => (int) $r['rule_id'], array_filter((array) $ev['rules'], fn ($r) => ! empty($r['applied']) && (int) $r['amount'] > 0))),
                'dropped' => (array) ($ev['dropped'] ?? []), 'alone' => $ev['alone'] ?? null,
            ];
        }
        $coupon = $this->coupon();
        $c = $coupon ? $coupon->discountFor($this->discountableSubtotal()) : 0;
        $cx = $coupon && $coupon->exclusive && $c > 0;
        $rules = collect($this->ruleResults()['discounts'])->filter(fn ($d) => (int) ($d['amount'] ?? 0) > 0);
        $shared = $rules->reject(fn ($d) => ! empty($d['exclusive']));
        $cap = $this->discountCap();

        $options = [['coupon' => ! $cx, 'rules' => $shared->pluck('rule_id')->all(), 'amount' => ($cx ? 0 : $c) + (int) $shared->sum('amount'), 'alone' => null]];
        // A «use alone» product sale is several rules that count together.
        foreach ($rules->filter(fn ($d) => ! empty($d['exclusive']))->groupBy(fn ($d) => $d['group'] ?? 'rule:'.$d['rule_id']) as $group) {
            $options[] = ['coupon' => false, 'rules' => $group->pluck('rule_id')->all(), 'amount' => (int) $group->sum('amount'),
                'alone' => '«'.preg_replace('/ · .*$/u', '', (string) ($group->first()['rule_name'] ?? '')).'»'];
        }
        if ($cx) {
            $options[] = ['coupon' => true, 'rules' => [], 'amount' => $c, 'alone' => 'کد «'.$coupon->code.'»'];
        }
        $best = $options[0];
        foreach ($options as $o) {
            if (min($o['amount'], $cap) > min($best['amount'], $cap)) {
                $best = $o;
            }
        }

        $dropped = $rules->reject(fn ($d) => in_array($d['rule_id'], $best['rules'], true))->pluck('rule_name')->map(fn ($n) => '«'.$n.'»')->all();
        if ($c > 0 && ! $best['coupon']) {
            array_unshift($dropped, 'کد «'.$coupon->code.'»');
        }

        return ['coupon' => $best['coupon'], 'rules' => $best['rules'], 'dropped' => $dropped, 'alone' => $best['alone']];
    }

    /** The coupon that actually counts on this order (null when «use alone» set it aside). */
    public function appliedCoupon(): ?Coupon
    {
        if (StoqsPromotions::managed()) {
            $c = collect((array) $this->stoqs()['codes'])->first(fn ($c) => ! empty($c['applied']));

            return $c && ($row = StoqsCart::findCode((string) $c['code'])) ? StoqsCart::couponModel($row) : null;
        }
        $coupon = $this->coupon();
        if (! $coupon || $coupon->isFreeShipping()) {
            return $coupon;
        }

        return $this->discountChoice()['coupon'] ? $coupon : null;
    }

    /** One sentence for the customer when «use alone» left a discount out; null otherwise. */
    public function discountNotice(): ?string
    {
        if (StoqsPromotions::managed()) {
            return $this->isEmpty() ? null : ($this->stoqs()['notice'] ?? null);
        }
        $choice = $this->discountChoice();
        if (! $choice['dropped']) {
            return null;
        }
        $names = implode('، ', array_slice($choice['dropped'], 0, 3));

        return $choice['alone']
            ? $choice['alone'].' با تخفیف‌های دیگر جمع نمی‌شود؛ چون بیشتر است، فقط همین حساب شد و '.$names.' حساب نشد.'
            : $names.' با تخفیف‌های دیگر جمع نمی‌شود؛ تخفیف‌های دیگر سبد بیشتر بود و همان‌ها حساب شد.';
    }

    /* ---------------------------- Gift cards --------------------------- */

    public function giftCard(): ?\App\Models\GiftCard
    {
        $code = $this->session->get(self::GIFT);
        if (! $code) {
            return null;
        }
        $card = \App\Models\GiftCard::findByCode($code);

        return ($card && $card->isUsable()) ? $card : null;
    }

    /** @return array{ok: bool, message: string} */
    public function applyGiftCard(string $code): array
    {
        $card = \App\Models\GiftCard::findByCode($code);
        if (! $card || ! $card->isUsable()) {
            return ['ok' => false, 'message' => 'کارت هدیه نامعتبر یا منقضی است.'];
        }
        $this->session->put(self::GIFT, $card->code);

        return ['ok' => true, 'message' => 'کارت هدیه اعمال شد.'];
    }

    public function removeGiftCard(): void
    {
        $this->session->forget(self::GIFT);
    }

    /* ----------------------- Combined totals -------------------------- */

    /** Coupon-only discount (backward compatible; use combinedDiscount() for total). */
    public function discount(): int
    {
        if (StoqsPromotions::managed()) {   // what the codes got, line by line
            return (int) collect((array) $this->stoqs()['lines'])->flatMap(fn ($l) => (array) ($l['parts'] ?? []))
                ->where('type', 'code')->sum('amount');
        }
        $coupon = $this->appliedCoupon();

        return $coupon ? $coupon->discountFor($this->discountableSubtotal()) : 0;
    }

    /** Total discount from all sources (coupon + discount rules), capped to subtotal.
     *  Promotion engine was merged into the discount-rule engine in R-10. */
    public function combinedDiscount(): int
    {
        if (StoqsPromotions::managed()) {
            return $this->isEmpty() ? 0 : (int) $this->stoqs()['discount'];
        }

        return (int) min($this->discount() + $this->ruleDiscount(), $this->discountCap());
    }

    /**
     * The most this cart may be discounted: line by line, the smaller of
     * shop.max_discount_percent of the line and what keeps that line at the
     * minimum margin over its finished cost, or at the StoqS floor_price when it has
     * no local cost (a line with neither has only the percent cap). So no product is sold below its floor, and a priced line
     * never borrows room from another.
     */
    public function discountCap(): int
    {
        if (StoqsPromotions::managed()) {
            return $this->isEmpty() ? 0 : (int) $this->stoqs()['cap'];
        }
        $percent = (int) floor($this->discountableSubtotal() * config('shop.max_discount_percent', 100) / 100);
        $floors = \App\Support\Margin::floors();
        if ($floors === []) {
            return $percent;
        }
        $max = (int) config('shop.max_discount_percent', 100);
        $room = 0;
        foreach ($this->lines() as $l) {
            $product = $l['variant']->product;
            if (! self::isDiscountable($product)) {
                continue;
            }
            $line = (int) $l['line_total'];
            $floor = $floors[(int) $product->id] ?? null;
            $room += min((int) floor($line * $max / 100),
                $floor === null ? $line : max(0, $line - $floor * (int) $l['quantity']));
        }

        return min($percent, $room);
    }

    /**
     * What each line got, for the StoqS sale report (items[].discounts): the outlet
     * markdown (price_sheet: struck price − outlet price), and the coupon and each
     * counted rule (campaign rules as campaign) spread over the lines they apply to
     * in proportion to the line totals, scaled down when the cap cut the total.
     * Shop rule ids are not StoqS ids, so rules carry no id.
     *
     * @return array<int, array<int, array{type: string, code?: string, amount: int}>> variant id => discounts
     */
    public function lineDiscounts(): array
    {
        if (StoqsPromotions::managed()) {
            return $this->isEmpty() ? [] : StoqsCart::lineDiscounts($this->stoqs());
        }
        $lines = $this->lines();
        $out = [];
        foreach ($lines as $l) {
            $p = $l['variant']->product;
            if (\App\Support\Outlet::has((int) $p->id) && (int) $p->compare_at_price > (int) $l['unit_price']) {
                $out[$l['variant']->id][] = ['type' => 'price_sheet', 'amount' => ((int) $p->compare_at_price - (int) $l['unit_price']) * (int) $l['quantity']];
            }
        }
        $disc = $lines->filter(fn ($l) => self::isDiscountable($l['variant']->product))->values();
        $sources = [];
        if (($coupon = $this->appliedCoupon()) && ($amount = $this->discount()) > 0) {
            $sources[] = [['type' => 'code', 'code' => $coupon->code], $amount, $disc];
        }
        $breakdown = array_filter($this->ruleDiscountBreakdown(), fn ($d) => (int) ($d['amount'] ?? 0) > 0);
        $rules = \App\Models\DiscountRule::whereIn('id', array_column($breakdown, 'rule_id'))->get()->keyBy('id');
        foreach ($breakdown as $d) {
            $rule = $rules[$d['rule_id']] ?? null;
            $type = str_starts_with((string) $rule?->description, 'campaign:') ? 'campaign' : 'rule';
            $on = $rule ? app(DiscountRuleEngine::class)->ruleLines($rule, $lines) : $disc;
            $sources[] = [['type' => $type], (int) $d['amount'], $on->isNotEmpty() ? $on : $disc];
        }
        // Rules bound to some lines go first, so a cart-wide coupon or rule spreads over
        // what room is left instead of squeezing them out (usort is stable).
        usort($sources, fn ($a, $b) => ($a[2]->count() < $disc->count() ? 0 : 1) <=> ($b[2]->count() < $disc->count() ? 0 : 1));
        $total = array_sum(array_column($sources, 1));
        $given = $this->combinedDiscount();
        if ($total > $given && $total > 0) {   // the cap cut it: scale, the last source takes the rounding
            $left = $given;
            foreach ($sources as $i => $s) {
                $sources[$i][1] = $i === array_key_last($sources) ? $left : (int) floor($s[1] * $given / $total);
                $left -= $sources[$i][1];
            }
        }
        // A line never takes more than its total (StoqS refuses discount_exceeds_line):
        // a capped share moves to the source's other lines, and what none can take is dropped.
        $room = $lines->mapWithKeys(fn ($l) => [$l['variant']->id => (int) $l['line_total']])->all();
        foreach ($sources as [$tag, $amount, $on]) {
            $on = $on->values();
            $sum = (int) $on->sum('line_total');
            $left = $amount;
            $got = [];
            foreach ($on as $i => $l) {
                $id = $l['variant']->id;
                $share = $i === $on->count() - 1 ? $left : min($left, (int) floor($amount * $l['line_total'] / max(1, $sum)));
                $share = max(0, min($share, $room[$id] ?? 0));
                $got[$id] = ($got[$id] ?? 0) + $share;
                $room[$id] = ($room[$id] ?? 0) - $share;
                $left -= $share;
            }
            foreach ($on as $l) {
                $id = $l['variant']->id;
                $more = min($left, $room[$id] ?? 0);
                if ($more > 0) {
                    $got[$id] += $more;
                    $room[$id] -= $more;
                    $left -= $more;
                }
            }
            foreach ($got as $id => $share) {
                if ($share > 0) {
                    $out[$id][] = $tag + ['amount' => $share];
                }
            }
        }

        return $out;
    }

    /** Total surcharge from discount rules. */
    public function surcharge(): int
    {
        return $this->ruleSurcharge();
    }

    public function hasFreeShipping(): bool
    {
        if ($this->coupon()?->isFreeShipping()) {
            return true;
        }

        return $this->ruleResults()['shipping_free'] ?? false;
    }

    /** Items total after discounts plus surcharges (shipping added at checkout). */
    public function total(): int
    {
        return max(0, $this->subtotal() - $this->combinedDiscount() + $this->surcharge());
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /** @return Collection<int, array{variant: ProductVariant, quantity: int, unit_price: int, line_total: int}> */
    public function lines(): Collection
    {
        $items = $this->raw();
        if ($items === []) {
            return collect();
        }

        $variants = ProductVariant::with(['product.images'])
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $changed = false;
        $lines = collect();

        foreach ($items as $variantId => $qty) {
            $variant = $variants->get($variantId);
            if (! $variant || ! $variant->is_active || ! $variant->product?->is_active) {
                unset($items[$variantId]);
                $changed = true;
                continue;
            }

            // availableQty() returns child-derived stock for a bundle's placeholder
            // variant, and plain stock_qty otherwise — one call covers both.
            $avail = $variant->availableQty();
            if ($avail > 0 && $qty > $avail) {
                $qty = $avail;
                $items[$variantId] = $qty;
                $changed = true;
            }

            $unit = $variant->effectivePrice();
            $lines->push([
                'variant' => $variant,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $unit * $qty,
            ]);
        }

        if ($changed) {
            $this->persist($items);
        }

        return $lines;
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum('line_total');
    }

    /** Subtotal of the lines coupons and discount rules may touch: everything
     *  except products in config('shop.no_discount_categories') (winter items). */
    public function discountableSubtotal(): int
    {
        return (int) $this->lines()->filter(fn ($l) => self::isDiscountable($l['variant']->product))->sum('line_total');
    }

    /** Bundles are already priced down, and winter categories are never discounted. */
    public static function isDiscountable($product): bool
    {
        if (! $product || $product->is_bundle || \App\Support\Outlet::has((int) $product->id)) {
            return false;
        }
        $skip = config('shop.no_discount_categories', []);

        return $skip === [] || $product->categories->pluck('slug')->push($product->category?->slug)->intersect($skip)->isEmpty();
    }

    /**
     * The nearest single-step rule this cart has not reached yet, for the
     * «n more» nudge: at most 3 items or half the threshold away.
     *
     * @return array{items: bool, gap: int, label: string, text: string}|null
     */
    public function nextOffer(): ?array
    {
        if ($this->isEmpty() || ! Schema::hasTable('discount_rules')) {
            return null;
        }
        if (StoqsPromotions::managed()) {
            $best = StoqsCart::nextOffer($this->stoqs(), $this->lines());
            if (! $best) {
                return null;
            }
            $best['text'] = ($best['items'] ? \App\Support\Money::toPersianDigits((string) $best['gap']).' قطعهٔ دیگر' : \App\Support\Money::toman($best['gap']).' دیگر')
                .' اضافه کنید: '.$best['label'];

            return $best;
        }
        $applied = collect($this->ruleDiscountBreakdown())->pluck('rule_id')->all();
        $lines = $this->lines();
        $best = null;

        foreach (\App\Models\DiscountRule::active()->get() as $rule) {
            $conds = $rule->conditions ?? [];
            if (count($conds) !== 1 || in_array($rule->id, $applied) || blank($rule->name)) {
                continue;
            }
            $c = $conds[0];
            $min = (int) ($c['min'] ?? 0);
            $have = match ($c['type'] ?? '') {
                'cart_item_count' => (int) $lines->sum('quantity'),
                'cart_subtotal' => (int) $lines->sum('line_total'),
                'item_count_from_categories' => (int) $lines->filter(fn ($l) => $l['variant']->product->categories->pluck('id')
                    ->push($l['variant']->product->category_id)->map(fn ($i) => (int) $i)->intersect(array_map('intval', $c['category_ids'] ?? []))->isNotEmpty())->sum('quantity'),
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
                $best = ['items' => $items, 'gap' => $gap, 'label' => $rule->name];
            }
        }
        if (! $best) {
            return null;
        }
        $best['text'] = ($best['items'] ? \App\Support\Money::toPersianDigits((string) $best['gap']).' قطعهٔ دیگر' : \App\Support\Money::toman($best['gap']).' دیگر')
            .' اضافه کنید: '.$best['label'];

        return $best;
    }
}
