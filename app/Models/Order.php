<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'در انتظار پرداخت',
        self::STATUS_PAID => 'پرداخت شده',
        self::STATUS_FAILED => 'ناموفق',
        self::STATUS_CANCELED => 'لغو شده',
        self::STATUS_PROCESSING => 'در حال آماده‌سازی',
        self::STATUS_SHIPPED => 'ارسال شده',
        self::STATUS_DELIVERED => 'تحویل شده',
    ];

    protected $fillable = [
        'number', 'user_id', 'status', 'subtotal', 'shipping_cost', 'discount', 'coupon_code', 'total',
        'shipping_method_id', 'shipping_method_name', 'shipping_cost_on_delivery', 'shipping_address',
        'customer_name', 'customer_phone', 'stockkeeping_sale_id',
        'stockkeeping_location', 'stockkeeping_location_name',
        'loyalty_points_used', 'loyalty_discount', 'gift_card_code', 'gift_used', 'placed_at', 'paid_at', 'customer_note',
        'applied_discount_rules',
        'gift_wrap', 'gift_wrap_price', 'gift_message',
    ];

    /** Human label for the StoqS location this order was fulfilled from. */
    public function fulfillmentLocationLabel(): ?string
    {
        if ($this->stockkeeping_location_name) {
            return $this->stockkeeping_location_name;
        }
        if (! $this->stockkeeping_location) {
            return null;
        }
        // Fall back to a readable form of the token, e.g. "warehouse:1" → "انبار ۱".
        [$type, $id] = array_pad(explode(':', $this->stockkeeping_location, 2), 2, '');
        $label = $type === 'shop' ? 'فروشگاه' : 'انبار';

        return trim($label.' '.$id);
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'shipping_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'applied_discount_rules' => 'array',
            'gift_wrap' => 'boolean',
            'gift_wrap_price' => 'integer',
            'shipping_cost_on_delivery' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID
            || in_array($this->status, [self::STATUS_PROCESSING, self::STATUS_SHIPPED, self::STATUS_DELIVERED], true);
    }

    public function formattedTotal(): string
    {
        return Money::toman($this->total);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public static function generateNumber(): string
    {
        return 'CH-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5));
    }
}
