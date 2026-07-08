<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    protected $fillable = [
        'code', 'description', 'type', 'value',
        'min_subtotal', 'max_subtotal', 'max_discount',
        'usage_limit', 'per_user_limit', 'used_count',
        'first_order_only', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_subtotal' => 'integer',
            'max_subtotal' => 'integer',
            'max_discount' => 'integer',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'first_order_only' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', mb_strtoupper(trim($code)))->first();
    }

    public function isFreeShipping(): bool
    {
        return $this->type === self::TYPE_FREE_SHIPPING;
    }

    /**
     * Why this coupon can't be used (null = valid). Checks date window, price
     * range, total + per-user usage, and first-order-only.
     */
    public function reasonInvalidFor(int $subtotal, ?User $user = null): ?string
    {
        if (! $this->is_active) {
            return 'این کد تخفیف فعال نیست.';
        }
        if ($this->starts_at && now()->lt($this->starts_at)) {
            return 'این کد تخفیف هنوز فعال نشده است.';
        }
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return 'این کد تخفیف منقضی شده است.';
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'ظرفیت استفاده از این کد به پایان رسیده است.';
        }
        if ($subtotal < $this->min_subtotal) {
            return 'حداقل مبلغ سفارش برای این کد '.Money::toman($this->min_subtotal).' است.';
        }
        if ($this->max_subtotal !== null && $subtotal > $this->max_subtotal) {
            return 'این کد فقط برای سفارش‌های تا '.Money::toman($this->max_subtotal).' معتبر است.';
        }
        if ($user) {
            if ($this->first_order_only && $this->userHasPaidOrder($user)) {
                return 'این کد فقط برای اولین خرید است.';
            }
            if ($this->per_user_limit !== null && $this->userRedemptions($user) >= $this->per_user_limit) {
                return 'سقف استفاده‌ی شما از این کد تکمیل شده است.';
            }
        }

        return null;
    }

    public function isValidFor(int $subtotal, ?User $user = null): bool
    {
        return $this->reasonInvalidFor($subtotal, $user) === null;
    }

    /** Discount amount (Toman) on the items subtotal (0 for free-shipping coupons). */
    public function discountFor(int $subtotal): int
    {
        if ($this->isFreeShipping()) {
            return 0;
        }
        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotal * $this->value / 100)
            : (int) $this->value;

        if ($this->max_discount !== null && $this->max_discount > 0) {
            $discount = min($discount, $this->max_discount);
        }

        return max(0, min($discount, $subtotal));
    }

    private function userHasPaidOrder(User $user): bool
    {
        return $user->orders()
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->exists();
    }

    private function userRedemptions(User $user): int
    {
        return $user->orders()->where('coupon_code', $this->code)->count();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_FIXED => 'مبلغ ثابت',
            self::TYPE_FREE_SHIPPING => 'ارسال رایگان',
            default => 'درصدی',
        };
    }
}
