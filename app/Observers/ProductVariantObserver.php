<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\StockNotification;
use App\Services\Sms\SmsService;
use Throwable;

/**
 * Fires the «back in stock» SMS when a variant's stock_qty crosses from
 * zero to positive. Notifications are one-shot — every row that matched
 * gets its phone texted, then the row is deleted so a future re-stock
 * doesn't re-text the same shopper.
 */
class ProductVariantObserver
{
    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock_qty')) {
            return;
        }
        $oldQty = (int) $variant->getOriginal('stock_qty');
        $newQty = (int) $variant->stock_qty;
        if ($oldQty > 0 || $newQty <= 0) {
            return;
        }
        $this->fanOut($variant);
    }

    private function fanOut(ProductVariant $variant): void
    {
        $alerts = StockNotification::where('product_variant_id', $variant->id)
            ->where('notified', false)
            ->get();
        if ($alerts->isEmpty()) {
            return;
        }

        $variant->loadMissing('product');
        $name = $variant->product?->name ?? 'محصول';
        $url = $variant->product ? url('/product/'.$variant->product->slug) : url('/');
        $size = $variant->size ? ' '.$variant->size : '';
        $color = $variant->color ? ' '.$variant->color : '';
        $body = "«{$name}»{$color}{$size} دوباره موجود شد 🎉\nخرید: {$url}";

        $sms = app(SmsService::class);
        foreach ($alerts as $alert) {
            try {
                $sms->send($alert->phone, $body, 'back_in_stock');
            } catch (Throwable $e) {
                report($e); // SMS glitches mustn't roll back the stock save
                continue;
            }
            $alert->forceFill(['notified' => true, 'notified_at' => now()])->save();
        }
    }
}
