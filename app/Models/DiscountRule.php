<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DiscountRule extends Model
{
    public const APPLY_MODE_ALL = 'all';
    public const APPLY_MODE_ANY = 'any';
    public const STACK_MODE_BEST = 'best';
    public const STACK_MODE_ALL = 'all';

    protected $fillable = [
        'name', 'description', 'priority', 'conditions', 'actions',
        'apply_mode', 'stack_mode', 'starts_at', 'expires_at',
        'max_uses', 'max_uses_per_user', 'used_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public static function conditionOptions(): array
    {
        return [
            'cart_subtotal' => ['label' => 'مبلغ خرید', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'cart_subtotal_excluding_sale' => ['label' => 'مبلغ خرید غیر فروش ویژه', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'cart_item_count' => ['label' => 'تعداد اقلام سفارش', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'cart_weight' => ['label' => 'وزن کالاهای سفارش', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'coupon_code' => ['label' => 'کوپن تخفیف', 'params' => ['code' => 'string']],
            'coupon_single_use' => ['label' => 'کوپن یکبار مصرف', 'params' => []],
            'coupon_single_use_per_user' => ['label' => 'کوپن یکبار مصرف برای هر کاربر', 'params' => []],
            'item_products' => ['label' => 'محصول خریداری شده', 'params' => ['product_ids' => 'array']],
            'item_brands' => ['label' => 'برند محصول', 'params' => ['brands' => 'array']],
            'item_categories' => ['label' => 'بخش محصول', 'params' => ['category_ids' => 'array']],
            'item_collections' => ['label' => 'مجموعه محصول', 'params' => ['collection_ids' => 'array']],
            'has_featured' => ['label' => 'حاوی محصول ویژه', 'params' => []],
            'has_sale_item' => ['label' => 'حاوی محصول فروش ویژه', 'params' => []],
            'has_discounted_item' => ['label' => 'حاوی محصول تخفیف خورده', 'params' => []],
            'has_full_price_item' => ['label' => 'حاوی محصول بدون تخفیف', 'params' => []],
            'item_count_from_products' => ['label' => 'تعداد اقلام از محصولات مشخص', 'params' => ['product_ids' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_total_from_products' => ['label' => 'مبلغ اقلام از محصولات مشخص', 'params' => ['product_ids' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_count_from_brands' => ['label' => 'تعداد اقلام از برندهای مشخص', 'params' => ['brands' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_total_from_brands' => ['label' => 'قیمت اقلام از برندهای مشخص', 'params' => ['brands' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_count_from_categories' => ['label' => 'تعداد اقلام از بخش‌های مشخص', 'params' => ['category_ids' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_total_from_categories' => ['label' => 'قیمت اقلام از بخش‌های مشخص', 'params' => ['category_ids' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_count_from_collections' => ['label' => 'تعداد اقلام از مجموعه‌های مشخص', 'params' => ['collection_ids' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'item_total_from_collections' => ['label' => 'قیمت اقلام از مجموعه‌های مشخص', 'params' => ['collection_ids' => 'array', 'min' => 'integer', 'max' => 'integer']],
            'user_status' => ['label' => 'وضعیت کاربر', 'params' => ['status' => ['type' => 'select', 'options' => ['guest' => 'مهمان', 'registered' => 'ثبت‌نام کرده', 'verified' => 'تأیید شده']]]],
            'user_group' => ['label' => 'گروه کاربری', 'params' => ['group_ids' => 'array']],
            'user_device' => ['label' => 'دیوایس کاربر', 'params' => ['device' => ['type' => 'select', 'options' => ['mobile' => 'موبایل', 'desktop' => 'دسکتاپ']]]],
            'user_previous_order_count' => ['label' => 'خرید قبلی کاربر (تعداد)', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'user_previous_order_total' => ['label' => 'مبلغ خرید قبلی کاربر', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'user_phone' => ['label' => 'شماره موبایل کاربر', 'params' => ['phones' => 'array', 'mode' => ['type' => 'select', 'options' => ['allow' => 'مجاز', 'deny' => 'ممنوع']]]],
            'user_city' => ['label' => 'شهر مبدأ خرید کاربر', 'params' => ['cities' => 'array']],
            'order_payment_method' => ['label' => 'روش پرداخت سفارش', 'params' => ['method_keys' => 'array']],
            'order_shipping_method' => ['label' => 'روش ارسال سفارش', 'params' => ['method_ids' => 'array']],
        ];
    }

    public static function actionOptions(): array
    {
        return [
            'cart_discount_percent' => ['label' => 'تخفیف روی سبد به درصد', 'params' => ['value' => 'integer']],
            'cart_discount_fixed' => ['label' => 'تخفیف روی سبد به تومان', 'params' => ['value' => 'integer']],
            'item_discount_percent' => ['label' => 'تخفیف روی کالا(ها) به درصد', 'params' => ['value' => 'integer']],
            'item_discount_fixed' => ['label' => 'تخفیف روی کالا(ها) به تومان', 'params' => ['value' => 'integer']],
            'discount_cap' => ['label' => 'سقف تخفیف', 'params' => ['value' => 'integer']],
            'cost_cap' => ['label' => 'سقف هزینه', 'params' => ['min' => 'integer', 'max' => 'integer']],
            'block_purchase' => ['label' => 'ممانعت از خرید', 'params' => ['message' => 'string']],
            'free_shipping' => ['label' => 'ارسال رایگان', 'params' => []],
            'item_surcharge_percent' => ['label' => 'افزایش هزینه روی کالاها به درصد', 'params' => ['value' => 'integer']],
            'item_surcharge_fixed' => ['label' => 'افزایش هزینه روی کالاها به تومان', 'params' => ['value' => 'integer']],
            'cart_surcharge_fixed' => ['label' => 'افزایش هزینه روی سبد به تومان', 'params' => ['value' => 'integer']],
            'cart_surcharge_percent' => ['label' => 'افزایش هزینه روی سبد به درصد', 'params' => ['value' => 'integer']],
        ];
    }

    public function conditionLabel(string $type): string
    {
        return static::conditionOptions()[$type]['label'] ?? $type;
    }

    public function actionLabel(string $type): string
    {
        return static::actionOptions()[$type]['label'] ?? $type;
    }
}
