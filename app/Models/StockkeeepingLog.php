<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockkeeepingLog extends Model
{
    public const TYPE_SALE_PUSH = 'sale_push';
    public const TYPE_CANCEL_RESTOCK = 'cancel_restock';
    public const TYPE_RETURN_RESTOCK = 'return_restock';
    public const TYPE_WEBHOOK_IN = 'webhook_in';
    public const TYPE_STOCK_PULL = 'stock_pull';
    public const TYPE_CATALOG_IMPORT = 'catalog_import';
    public const TYPE_COLLECTIONS_IMPORT = 'collections_import';
    public const TYPE_WEBHOOK_PRODUCT = 'webhook_product';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AUTO = 'auto';

    public const TYPE_LABELS = [
        self::TYPE_SALE_PUSH => 'ثبت فروش',
        self::TYPE_CANCEL_RESTOCK => 'بازگشت موجودی (لغو)',
        self::TYPE_RETURN_RESTOCK => 'بازگشت موجودی (مرجوعی)',
        self::TYPE_WEBHOOK_IN => 'تغییر موجودی (وب‌هوک)',
        self::TYPE_STOCK_PULL => 'همگام‌سازی موجودی',
        self::TYPE_CATALOG_IMPORT => 'وارد کردن کاتالوگ',
        self::TYPE_COLLECTIONS_IMPORT => 'وارد کردن مجموعه‌ها',
        self::TYPE_WEBHOOK_PRODUCT => 'محصول جدید/به‌روز (وب‌هوک)',
    ];

    protected $table = 'stockkeeping_log';

    protected $fillable = [
        'event_type', 'direction', 'ref', 'order_id', 'payload', 'status', 'remote_id', 'error',
        'source', 'summary', 'duration_ms',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Shorthand to write a log entry. */
    public static function record(
        string $eventType,
        string $direction,
        ?string $ref,
        ?int $orderId,
        array $payload,
        string $status = 'ok',
        ?string $remoteId = null,
        ?string $error = null,
        ?string $source = null,
        ?string $summary = null,
        ?int $durationMs = null,
    ): static {
        return static::create([
            'event_type' => $eventType,
            'direction' => $direction,
            'ref' => $ref,
            'order_id' => $orderId,
            'payload' => $payload,
            'status' => $status,
            'remote_id' => $remoteId,
            'error' => $error,
            'source' => $source,
            'summary' => $summary,
            'duration_ms' => $durationMs,
        ]);
    }
}
