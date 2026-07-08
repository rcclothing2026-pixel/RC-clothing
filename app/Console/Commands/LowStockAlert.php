<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Telegram\TelegramNotifier;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Daily low-stock digest to the Telegram admins: variants of published products
 * at or below the configured threshold. Silent when nothing is low.
 */
class LowStockAlert extends Command
{
    protected $signature = 'alerts:low-stock';

    protected $description = 'Send a low-stock digest to Telegram admins';

    public function handle(TelegramNotifier $telegram): int
    {
        if (! $telegram->eventEnabled('low_stock')) {
            return self::SUCCESS;
        }

        $threshold = (int) Setting::get('alerts.low_stock_threshold', 3);

        $low = ProductVariant::query()
            ->where('is_active', true)
            ->where('stock_qty', '<=', $threshold)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product:id,name')
            ->orderBy('stock_qty')
            ->limit(40)
            ->get();

        if ($low->isEmpty()) {
            $this->info('No low-stock items.');

            return self::SUCCESS;
        }

        $lines = ['⚠️ <b>کالاهای رو به اتمام</b> (آستانه '.Money::toPersianDigits((string) $threshold).')', ''];
        foreach ($low as $v) {
            $name = $v->product?->name.($v->size ? ' '.$v->size : '').($v->color ? ' / '.$v->color : '');
            $lines[] = '• '.$name.' — موجودی '.Money::toPersianDigits((string) $v->stock_qty);
        }

        $sent = $telegram->broadcast(implode("\n", $lines));
        $this->info("Low-stock alert sent to {$sent} admin chat(s).");

        return self::SUCCESS;
    }
}
