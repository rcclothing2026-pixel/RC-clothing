<?php

namespace App\Services\StockKeeping;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * No-op client used until the real stock-keeping API is wired
 * (STOCKKEEPING_ENABLED=false). It logs calls and uses the website's local
 * cached stock so the storefront is fully functional during development.
 */
class NullStockKeepingClient implements StockKeepingClient
{
    public function fetchStock(array $variantIds): array
    {
        // No remote source yet — caller falls back to local cached stock.
        return [];
    }

    public function reportSale(array $sale): string
    {
        Log::info('[stock-keeping:stub] reportSale', $sale);

        return 'stub_'.Str::uuid();
    }

    public function reportIncome(array $income): string
    {
        Log::info('[stock-keeping:stub] reportIncome', $income);

        return 'stub_'.Str::uuid();
    }

    public function upsertCustomer(array $customer): string
    {
        Log::info('[stock-keeping:stub] upsertCustomer', $customer);

        return 'stub_'.Str::uuid();
    }

    public function findCustomer(string $phone): ?array
    {
        return null;
    }

    public function adjustStock(array $items, string $reason = 'website_adjust', ?string $externalRef = null): bool
    {
        return false;
    }

    public function inventory(?string $since = null, int $perPage = 500): array
    {
        return [];
    }

    public function listLocations(): array
    {
        return [];
    }

    public function catalog(?string $since = null, int $perPage = 200): array
    {
        return [];
    }

    public function collections(): array
    {
        return [];
    }
}
