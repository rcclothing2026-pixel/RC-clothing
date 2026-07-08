<?php

namespace App\Services\StockKeeping;

/**
 * Contract for talking to the Chiaco Stock-Keeping SaaS (POS + inventory).
 *
 * This is the single seam between the website and the external system of
 * record. Everything the website needs from stock-keeping goes through here,
 * so wiring the real API later means implementing this interface only — no
 * changes anywhere else in the app.
 *
 * NOTE: method shapes are our intended contract. They will be confirmed/adjusted
 * once the stock-keeping API is available (see PLAN.md, open question #5).
 */
interface StockKeepingClient
{
    /**
     * Read live stock levels for the given stock-keeping variant ids.
     *
     * @param  array<int|string>  $variantIds  stockkeeping_variant_id values
     * @return array<int|string, int>  variant id => available quantity
     */
    public function fetchStock(array $variantIds): array;

    /**
     * Report a completed sale/order to stock-keeping (decrements inventory and
     * records the sale for reporting). Returns the remote sale id.
     *
     * @param  array<string, mixed>  $sale
     */
    public function reportSale(array $sale): string;

    /**
     * Report income / a received payment for reporting & reconciliation.
     *
     * @param  array<string, mixed>  $income
     */
    public function reportIncome(array $income): string;

    /**
     * Create or update a customer record (CRM) in stock-keeping.
     *
     * @param  array<string, mixed>  $customer
     * @return string  remote customer id
     */
    public function upsertCustomer(array $customer): string;

    /**
     * Look a customer up by phone — returns their CRM profile (loyalty, lifetime
     * value, recent purchases) or null if unknown / unavailable.
     *
     * @return array<string, mixed>|null
     */
    public function findCustomer(string $phone): ?array;

    /**
     * Adjust stock at the fulfilment location (e.g. restock a return). Items are
     * [{variant_id|barcode, delta}]. Returns true on success.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function adjustStock(array $items, string $reason = 'website_adjust', ?string $externalRef = null): bool;

    /**
     * Pull the full inventory (catalog + stock) across configured locations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inventory(?string $since = null, int $perPage = 500): array;

    /**
     * List warehouses + shops the API key may use.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listLocations(): array;

    /**
     * Pull products flagged "Send to website" in StoqS.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(?string $since = null, int $perPage = 200): array;

    /**
     * Pull product collections/groups from StoqS.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collections(): array;

}
