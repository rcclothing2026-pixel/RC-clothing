<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Telegram\TelegramNotifier;
use Throwable;

/**
 * Pushes a product card to the configured Telegram channel the FIRST time
 * the product becomes active. broadcast_at is the idempotency guard, so
 * re-toggling is_active or editing the product never re-broadcasts.
 */
class ProductObserver
{
    public function created(Product $product): void
    {
        if ($product->is_active) {
            $this->broadcast($product);
        }
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged('is_active') && $product->is_active && ! $product->broadcast_at) {
            $this->broadcast($product);
        }
    }

    private function broadcast(Product $product): void
    {
        try {
            if (app(TelegramNotifier::class)->broadcastNewProduct($product)) {
                $product->forceFill(['broadcast_at' => now()])->saveQuietly();
            }
        } catch (Throwable $e) {
            report($e); // never break product save on a Telegram glitch
        }
    }
}
