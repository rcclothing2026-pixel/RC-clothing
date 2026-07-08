<?php

namespace App\Services\Cart;

use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\SavedCart;
use App\Services\Discount\DiscountRuleEngine;
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

    private ?array $ruleResultsCache = null;

    public function __construct(private readonly Session $session) {}

    /** @return array<int,int> variantId => qty */
    private function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    private function persist(array $items): void
    {
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
        $coupon = Coupon::findByCode($code);
        if (! $coupon || ! $coupon->isValidFor($this->subtotal(), Auth::user())) {
            return null;
        }

        return $coupon;
    }

    /** @return array{ok: bool, message: string} */
    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::findByCode($code);
        if (! $coupon) {
            return ['ok' => false, 'message' => 'کد تخفیف نامعتبر است.'];
        }
        if ($reason = $coupon->reasonInvalidFor($this->subtotal(), Auth::user())) {
            return ['ok' => false, 'message' => $reason];
        }
        $this->session->put(self::COUPON, $coupon->code);

        return ['ok' => true, 'message' => 'کد تخفیف اعمال شد.'];
    }

    public function removeCoupon(): void
    {
        $this->session->forget(self::COUPON);
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

        if ($this->isEmpty() || ! Schema::hasTable('discount_rules')) {
            $this->ruleResultsCache = ['discounts' => [], 'blocks' => [], 'shipping_free' => false];
            return $this->ruleResultsCache;
        }

        $this->ruleResultsCache = app(DiscountRuleEngine::class)->evaluate($this, Auth::user(), $this->ruleContext);

        return $this->ruleResultsCache;
    }

    /** Total discount from rules (excluding surcharges, which appear as negative amounts). */
    public function ruleDiscount(): int
    {
        $total = 0;
        foreach ($this->ruleResults()['discounts'] as $d) {
            $amount = (int) ($d['amount'] ?? 0);
            if ($amount > 0) {
                $total += $amount;
            }
        }
        return $total;
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

    /** Breakdown of rule-based discounts for display. */
    public function ruleDiscountBreakdown(): array
    {
        return $this->ruleResults()['discounts'];
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
        $coupon = $this->coupon();

        return $coupon ? $coupon->discountFor($this->subtotal()) : 0;
    }

    /** Total discount from all sources (coupon + discount rules), capped to subtotal.
     *  Promotion engine was merged into the discount-rule engine in R-10. */
    public function combinedDiscount(): int
    {
        return (int) min($this->discount() + $this->ruleDiscount(), $this->subtotal());
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
}
