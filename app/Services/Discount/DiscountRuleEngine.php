<?php

namespace App\Services\Discount;

use App\Models\DiscountRule;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\Cart;
use Illuminate\Support\Collection;

class DiscountRuleEngine
{
    /**
     * Evaluate all active rules.
     *
     * @param  array{payment_method_key?: string, shipping_method_id?: int, shipping_city?: string}  $context
     * @return array{discounts: array, blocks: array, shipping_free: bool, surcharges: array}
     */
    public function evaluate(Cart $cart, ?User $user = null, array $context = []): array
    {
        $rules = DiscountRule::active()->orderBy('priority', 'desc')->get();
        $lines = $cart->lines();
        $subtotal = $cart->subtotal();
        $results = ['discounts' => [], 'blocks' => [], 'shipping_free' => false, 'surcharges' => []];

        foreach ($rules as $rule) {
            if ($rule->max_uses && $rule->used_count >= $rule->max_uses) {
                continue;
            }

            if (empty($rule->conditions)) {
                $this->applyActions($rule, $results, $lines, $subtotal);
                continue;
            }

            $applyMode = $rule->apply_mode === DiscountRule::APPLY_MODE_ALL;
            $pass = $applyMode;

            foreach ($rule->conditions as $cond) {
                $matches = $this->evaluateCondition($cond, $lines, $subtotal, $user, $cart, $context);
                if ($applyMode && !$matches) { $pass = false; break; }
                if (!$applyMode && $matches) { $pass = true; break; }
            }

            if (!$pass) continue;

            $this->applyActions($rule, $results, $lines, $subtotal);
        }

        return $results;
    }

    private function evaluateCondition(array $cond, Collection $lines, int $subtotal, ?User $user, Cart $cart, array $context): bool
    {
        return match ($cond['type']) {
            'cart_subtotal' => $this->rangeCheck($subtotal, $cond),
            'cart_subtotal_excluding_sale' => $this->rangeCheck($this->subtotalExcludingSale($lines), $cond),
            'cart_item_count' => $this->rangeCheck((int) $lines->sum('quantity'), $cond),
            'cart_weight' => $this->rangeCheck($this->totalWeight($lines), $cond),
            'coupon_code' => $this->checkCouponCode($cart, $cond['code'] ?? ''),
            'coupon_single_use' => $cart->coupon() !== null,
            'coupon_single_use_per_user' => $cart->coupon() !== null && $user !== null,
            'item_products' => $this->hasProducts($lines, $cond['product_ids'] ?? []),
            'item_brands' => $this->hasBrands($lines, $cond['brands'] ?? []),
            'item_categories' => $this->hasCategories($lines, $cond['category_ids'] ?? []),
            'item_collections' => $this->hasCollections($lines, $cond['collection_ids'] ?? []),
            'has_featured' => $this->hasFeatured($lines),
            'has_sale_item' => $this->hasSaleItem($lines),
            'has_discounted_item' => $this->hasDiscountedItem($lines),
            'has_full_price_item' => $this->hasFullPriceItem($lines),
            'item_count_from_products' => $this->rangeCheck($this->countForProducts($lines, $cond['product_ids'] ?? []), $cond),
            'item_total_from_products' => $this->rangeCheck($this->totalForProducts($lines, $cond['product_ids'] ?? []), $cond),
            'item_count_from_brands' => $this->rangeCheck($this->countForBrands($lines, $cond['brands'] ?? []), $cond),
            'item_total_from_brands' => $this->rangeCheck($this->totalForBrands($lines, $cond['brands'] ?? []), $cond),
            'item_count_from_categories' => $this->rangeCheck($this->countForCategories($lines, $cond['category_ids'] ?? []), $cond),
            'item_total_from_categories' => $this->rangeCheck($this->totalForCategories($lines, $cond['category_ids'] ?? []), $cond),
            'item_count_from_collections' => $this->rangeCheck($this->countForCollections($lines, $cond['collection_ids'] ?? []), $cond),
            'item_total_from_collections' => $this->rangeCheck($this->totalForCollections($lines, $cond['collection_ids'] ?? []), $cond),
            'user_status' => $this->checkUserStatus($user, $cond['status'] ?? 'registered'),
            'user_group' => $this->checkUserGroup($user, $cond['group_ids'] ?? []),
            'user_device' => $this->checkDevice($cond['device'] ?? ''),
            'user_previous_order_count' => $this->rangeCheck($user ? (int) $user->orders()->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])->count() : 0, $cond),
            'user_previous_order_total' => $this->rangeCheck($user ? (int) $user->orders()->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])->sum('total') : 0, $cond),
            'user_phone' => $this->checkPhone($user, $cond),
            'user_city' => $this->checkCity($user, $cond, $context),
            'order_payment_method' => $this->checkPaymentMethod($context, $cond['method_keys'] ?? []),
            'order_shipping_method' => $this->checkShippingMethod($context, $cond['method_ids'] ?? []),
            default => false,
        };
    }

    private function applyActions(DiscountRule $rule, array &$results, Collection $lines, int $subtotal): void
    {
        $discount = 0;

        foreach ($rule->actions as $i => $action) {
            if ($rule->stack_mode === DiscountRule::STACK_MODE_BEST && $i > 0) break;

            switch ($action['type']) {
                case 'cart_discount_percent':
                    $discount += (int) round($subtotal * ($action['value'] ?? 0) / 100);
                    break;
                case 'cart_discount_fixed':
                    $discount += (int) ($action['value'] ?? 0);
                    break;
                case 'item_discount_percent':
                    $discount += (int) round($subtotal * ($action['value'] ?? 0) / 100);
                    break;
                case 'item_discount_fixed':
                    $discount += (int) ($action['value'] ?? 0);
                    break;
                case 'discount_cap':
                    $discount = min($discount, (int) ($action['value'] ?? 0));
                    break;
                case 'cost_cap':
                    break;
                case 'block_purchase':
                    $results['blocks'][] = $action['message'] ?? 'خرید شما با محدودیت مواجه شد.';
                    break;
                case 'free_shipping':
                    $results['shipping_free'] = true;
                    break;
                case 'item_surcharge_percent':
                    $discount -= (int) round($subtotal * ($action['value'] ?? 0) / 100);
                    break;
                case 'item_surcharge_fixed':
                    $discount -= (int) ($action['value'] ?? 0);
                    break;
                case 'cart_surcharge_fixed':
                    $discount -= (int) ($action['value'] ?? 0);
                    break;
                case 'cart_surcharge_percent':
                    $discount -= (int) round($subtotal * ($action['value'] ?? 0) / 100);
                    break;
            }
        }

        if ($discount !== 0) {
            $results['discounts'][] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'amount' => $discount,
            ];
        }
    }

    /* ---- helpers ---- */

    private function rangeCheck(int $value, array $cond): bool
    {
        if (isset($cond['min']) && $value < (int) $cond['min']) return false;
        if (isset($cond['max']) && $value > (int) $cond['max']) return false;
        return true;
    }

    private function subtotalExcludingSale(Collection $lines): int
    {
        return $lines->reduce(fn ($s, $l) => $s + (($l['variant']->product->compare_at_price ?? 0) > 0 ? 0 : $l['line_total']), 0);
    }

    private function totalWeight(Collection $lines): int
    {
        return (int) $lines->sum(fn ($l) => ((int) ($l['variant']->weight ?? 0)) * $l['quantity']);
    }

    private function hasProducts(Collection $lines, array $ids): bool
    {
        if (empty($ids)) return false;
        $ids = array_map('intval', $ids);
        return $lines->contains(fn ($l) => in_array($l['variant']->product_id, $ids));
    }

    private function hasBrands(Collection $lines, array $brands): bool
    {
        if (empty($brands)) return false;
        $brands = array_map('trim', $brands);
        $brands = array_map(fn ($b) => mb_strtolower($b), $brands);
        return $lines->contains(fn ($l) => in_array(mb_strtolower(trim((string) ($l['variant']->product->brand ?? ''))), $brands, true));
    }

    private function hasCategories(Collection $lines, array $ids): bool
    {
        if (empty($ids)) return false;
        $ids = array_map('intval', $ids);
        return $lines->contains(fn ($l) => $this->productInCategories($l['variant']->product, $ids));
    }

    /** True if the product belongs to ANY of $ids — primary or secondary category. */
    private function productInCategories($product, array $ids): bool
    {
        if (! $product) return false;
        if (in_array((int) $product->category_id, $ids, true)) return true;

        return $product->categories->pluck('id')->map(fn ($i) => (int) $i)->intersect($ids)->isNotEmpty();
    }

    private function hasCollections(Collection $lines, array $ids): bool
    {
        if (empty($ids)) return false;
        $ids = array_map('intval', $ids);
        return $lines->contains(fn ($l) => in_array((int) $l['variant']->product->collection_id, $ids));
    }

    private function hasFeatured(Collection $lines): bool
    {
        return $lines->contains(fn ($l) => $l['variant']->product->is_featured ?? false);
    }

    private function hasSaleItem(Collection $lines): bool
    {
        return $lines->contains(fn ($l) => $l['variant']->product->hasDiscount());
    }

    private function hasDiscountedItem(Collection $lines): bool
    {
        return $lines->contains(fn ($l) => $l['variant']->product->hasDiscount());
    }

    private function hasFullPriceItem(Collection $lines): bool
    {
        return $lines->contains(fn ($l) => !$l['variant']->product->hasDiscount());
    }

    private function countForProducts(Collection $lines, array $ids): int
    {
        if (empty($ids)) return 0;
        $ids = array_map('intval', $ids);
        return (int) $lines->filter(fn ($l) => in_array($l['variant']->product_id, $ids))->sum('quantity');
    }

    private function totalForProducts(Collection $lines, array $ids): int
    {
        if (empty($ids)) return 0;
        $ids = array_map('intval', $ids);
        return (int) $lines->filter(fn ($l) => in_array($l['variant']->product_id, $ids))->sum('line_total');
    }

    private function countForBrands(Collection $lines, array $brands): int
    {
        if (empty($brands)) return 0;
        $brands = array_map(fn ($b) => mb_strtolower(trim($b)), $brands);
        return (int) $lines->filter(fn ($l) => in_array(mb_strtolower(trim((string) ($l['variant']->product->brand ?? ''))), $brands, true))->sum('quantity');
    }

    private function totalForBrands(Collection $lines, array $brands): int
    {
        if (empty($brands)) return 0;
        $brands = array_map(fn ($b) => mb_strtolower(trim($b)), $brands);
        return (int) $lines->filter(fn ($l) => in_array(mb_strtolower(trim((string) ($l['variant']->product->brand ?? ''))), $brands, true))->sum('line_total');
    }

    private function countForCategories(Collection $lines, array $ids): int
    {
        if (empty($ids)) return 0;
        $ids = array_map('intval', $ids);
        return (int) $lines->filter(fn ($l) => $this->productInCategories($l['variant']->product, $ids))->sum('quantity');
    }

    private function totalForCategories(Collection $lines, array $ids): int
    {
        if (empty($ids)) return 0;
        $ids = array_map('intval', $ids);
        return (int) $lines->filter(fn ($l) => $this->productInCategories($l['variant']->product, $ids))->sum('line_total');
    }

    private function countForCollections(Collection $lines, array $ids): int
    {
        if (empty($ids)) return 0;
        $ids = array_map('intval', $ids);
        return (int) $lines->filter(fn ($l) => in_array((int) $l['variant']->product->collection_id, $ids))->sum('quantity');
    }

    private function totalForCollections(Collection $lines, array $ids): int
    {
        if (empty($ids)) return 0;
        $ids = array_map('intval', $ids);
        return (int) $lines->filter(fn ($l) => in_array((int) $l['variant']->product->collection_id, $ids))->sum('line_total');
    }

    private function checkUserStatus(?User $user, string $status): bool
    {
        if ($status === 'guest') return $user === null;
        if ($status === 'registered') return $user !== null;
        if ($status === 'verified') return $user !== null && $user->phone_verified_at !== null;
        return false;
    }

    private function checkUserGroup(?User $user, array $groups): bool
    {
        if (empty($groups)) return false;
        if ($user === null) return false;
        $groups = array_map(fn ($g) => mb_strtolower(trim($g)), $groups);
        return in_array(mb_strtolower(trim((string) $user->group)), $groups, true);
    }

    private function checkDevice(string $device): bool
    {
        $ua = request()->userAgent() ?? '';
        $isMobile = (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua);
        return $device === 'mobile' ? $isMobile : !$isMobile;
    }

    private function checkPhone(?User $user, array $cond): bool
    {
        if ($user === null || !$user->phone) return ($cond['mode'] ?? 'allow') === 'deny';
        $phones = array_map('trim', $cond['phones'] ?? []);
        $match = in_array($user->phone, $phones);
        return ($cond['mode'] ?? 'allow') === 'allow' ? $match : !$match;
    }

    private function checkCity(?User $user, array $cond, array $context): bool
    {
        $cities = array_map(fn ($c) => mb_strtolower(trim($c)), $cond['cities'] ?? []);
        if (empty($cities)) return false;

        // At checkout the address may already be selected.
        if (!empty($context['shipping_city'])) {
            return in_array(mb_strtolower(trim($context['shipping_city'])), $cities, true);
        }

        // Fall back to the user's default address.
        if ($user) {
            $addr = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first();
            if ($addr && $addr->city) {
                return in_array(mb_strtolower(trim($addr->city)), $cities, true);
            }
        }

        return false;
    }

    private function checkPaymentMethod(array $context, array $keys): bool
    {
        if (empty($keys)) return false;
        if (empty($context['payment_method_key'])) return false;
        return in_array($context['payment_method_key'], $keys, true);
    }

    private function checkShippingMethod(array $context, array $ids): bool
    {
        if (empty($ids)) return false;
        if (empty($context['shipping_method_id'])) return false;
        $ids = array_map('intval', $ids);
        return in_array((int) $context['shipping_method_id'], $ids, true);
    }

    private function checkCouponCode(Cart $cart, string $code): bool
    {
        $applied = $cart->coupon();
        return $applied && mb_strtoupper(trim($applied->code)) === mb_strtoupper(trim($code));
    }
}
