<?php

namespace App\Services\StockKeeping;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decorator for StockKeepingClient that adds Redis caching and Circuit Breaker logic.
 *
 * This ensures that if the StoqS API is slow or down:
 * 1. The storefront remains fast by serving cached stock levels.
 * 2. API failures don't hang the request (Circuit Breaker).
 * 3. We fall back to local database stock levels when the API is unreachable.
 */
class ResilientStockKeepingClient implements StockKeepingClient
{
    private const CACHE_KEY_PREFIX = 'stockkeeping:stock:';
    private const CB_FAILURE_KEY = 'stockkeeping:cb:failures';
    private const CB_STATUS_KEY = 'stockkeeping:cb:status';

    public function __construct(
        private readonly StockKeepingClient $inner,
        private readonly int $cacheTtl = 120,
        private readonly bool $cbEnabled = true,
        private readonly int $failureThreshold = 5,
        private readonly int $resetTimeout = 60
    ) {}

    public function fetchStock(array $variantIds): array
    {
        if (empty($variantIds)) {
            return [];
        }

        // 1. Try to serve from cache first
        $cached = [];
        $missingIds = [];
        foreach ($variantIds as $id) {
            $val = Cache::get(self::CACHE_KEY_PREFIX . $id);
            if ($val !== null) {
                $cached[$id] = (int) $val;
            } else {
                $missingIds[] = $id;
            }
        }

        if (empty($missingIds)) {
            return $cached;
        }

        // 2. Check Circuit Breaker
        if ($this->isCircuitOpen()) {
            return $this->fallbackStock($variantIds, $cached);
        }

        try {
            // 3. Call the real API for missing IDs
            $remoteStock = $this->inner->fetchStock($missingIds);

            // 4. Update cache and reset failure count
            foreach ($remoteStock as $id => $qty) {
                Cache::put(self::CACHE_KEY_PREFIX . $id, $qty, $this->cacheTtl);
                $cached[$id] = $qty;
            }
            
            $this->recordSuccess();

            return $cached;
        } catch (Throwable $e) {
            $this->recordFailure($e);
            return $this->fallbackStock($variantIds, $cached);
        }
    }

    public function reportSale(array $sale): string
    {
        return $this->executeResiliently(fn() => $this->inner->reportSale($sale), 'reportSale');
    }

    public function reportIncome(array $income): string
    {
        return $this->executeResiliently(fn() => $this->inner->reportIncome($income), 'reportIncome');
    }

    public function upsertCustomer(array $customer): string
    {
        return $this->executeResiliently(fn() => $this->inner->upsertCustomer($customer), 'upsertCustomer');
    }

    public function findCustomer(string $phone): ?array
    {
        return $this->executeResiliently(fn() => $this->inner->findCustomer($phone), 'findCustomer');
    }

    public function adjustStock(array $items, string $reason = 'website_adjust', ?string $externalRef = null): bool
    {
        return $this->executeResiliently(fn() => $this->inner->adjustStock($items, $reason, $externalRef), 'adjustStock');
    }

    public function inventory(?string $since = null, int $perPage = 500): array
    {
        return $this->inner->inventory($since, $perPage);
    }

    public function listLocations(): array
    {
        return $this->inner->listLocations();
    }

    public function catalog(?string $since = null, int $perPage = 200): array
    {
        return $this->inner->catalog($since, $perPage);
    }

    public function collections(): array
    {
        return $this->inner->collections();
    }

    /**
     * Executes a call through the circuit breaker.
     */
    private function executeResiliently(callable $call, string $context)
    {
        if ($this->isCircuitOpen()) {
            throw new \RuntimeException("Circuit is open for StockKeeping: {$context}");
        }

        try {
            $result = $call();
            $this->recordSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }

    private function isCircuitOpen(): bool
    {
        if (!$this->cbEnabled) {
            return false;
        }

        return Cache::get(self::CB_STATUS_KEY) === 'open';
    }

    private function recordFailure(Throwable $e): void
    {
        if (!$this->cbEnabled) {
            return;
        }

        $failures = Cache::increment(self::CB_FAILURE_KEY);
        
        Log::warning("[stock-keeping] API failure ({$failures}/{$this->failureThreshold}): " . $e->getMessage());

        if ($failures >= $this->failureThreshold) {
            Cache::put(self::CB_STATUS_KEY, 'open', $this->resetTimeout);
            Log::error("[stock-keeping] Circuit Tripped! StoqS API is now isolated for {$this->resetTimeout}s.");
        }
    }

    private function recordSuccess(): void
    {
        if (!$this->cbEnabled) {
            return;
        }

        Cache::forget(self::CB_FAILURE_KEY);
        Cache::forget(self::CB_STATUS_KEY);
    }

    /**
     * Falls back to local database stock when the API is down.
     */
    private function fallbackStock(array $allIds, array $partiallyCached): array
    {
        $out = $partiallyCached;
        $stillMissing = array_diff($allIds, array_keys($partiallyCached));

        if (!empty($stillMissing)) {
            $variants = ProductVariant::whereIn('stockkeeping_variant_id', $stillMissing)
                ->orWhereIn('sku', $stillMissing)
                ->get();

            foreach ($variants as $v) {
                // Use local stock as fallback.
                $out[$v->stockkeeping_variant_id ?: $v->sku] = $v->stock_qty;
            }
        }

        return $out;
    }
}
