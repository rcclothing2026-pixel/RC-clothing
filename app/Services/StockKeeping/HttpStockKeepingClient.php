<?php

namespace App\Services\StockKeeping;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP implementation against the StoqS Brand API (v1).
 *
 * Auth is a single per-brand bearer key — the key *is* the tenant, so no extra
 * tenant header is needed. The key is also bound (server-side) to the set of
 * stock locations it may read/write, so this client only ever sees the stock
 * the operator allowed it to.
 *
 * Contract (see StoqS /api/v1):
 *   GET  /locations                          warehouses + shops the key may use
 *   GET  /inventory?locations=&since=&page=  aggregated catalog + stock
 *   POST /sales                              record + decrement a sale
 *   POST /customers                          CRM upsert by phone
 */
class HttpStockKeepingClient implements StockKeepingClient
{
    /**
     * @param  array<int, string>  $locations  location tokens ("warehouse:1","shop:3") the site mirrors
     * @param  string|null  $fulfillmentLocation  token sales are reported to (defaults to first location)
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly array $locations = [],
        private readonly ?string $fulfillmentLocation = null,
    ) {}

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->withHeader('X-Instance-ID', config('stockkeeping.instance_id', ''))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 200);
    }

    private function locationsParam(): string
    {
        return implode(',', $this->locations);
    }

    /** The location a sale is reported to / decremented from. */
    private function fulfillment(): string
    {
        return $this->fulfillmentLocation ?: ($this->locations[0] ?? '');
    }

    /**
     * Pull the full catalog+stock across the configured locations, following
     * pagination. Each item: variant_id, barcode/sku, product, size, color,
     * price, stock (summed across locations), active, updated_at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inventory(?string $since = null, int $perPage = 500): array
    {
        $items = [];
        $page = 1;
        do {
            $resp = $this->request()->get('/api/v1/inventory', array_filter([
                'locations' => $this->locationsParam() ?: null,
                'since' => $since,
                'page' => $page,
                'per_page' => $perPage,
            ]))->throw();

            $batch = $resp->json('items', []);
            foreach ($batch as $row) {
                $items[] = $row;
            }
            $hasMore = (bool) $resp->json('has_more', false);
            $page++;
        } while ($hasMore && $page < 200); // hard stop so a bug can't loop forever

        return $items;
    }

    /** Warehouses + shops the key may use: [{type,id,name,token}]. */
    public function listLocations(): array
    {
        return $this->request()->get('/api/v1/locations')->throw()->json('locations', []);
    }

    /**
     * Pull full product info for products flagged "Send to website" in StoqS,
     * following pagination. Each product carries its variants (barcode = sku).
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(?string $since = null, int $perPage = 200): array
    {
        $products = [];
        $page = 1;
        do {
            $resp = $this->request()->get('/api/v1/catalog', array_filter([
                'since' => $since,
                'page' => $page,
                'per_page' => $perPage,
            ]))->throw();

            foreach ($resp->json('products', []) as $row) {
                $products[] = $row;
            }
            $hasMore = (bool) $resp->json('has_more', false);
            $page++;
        } while ($hasMore && $page < 200);

        return $products;
    }

    public function fetchStock(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $want = array_flip(array_map('strval', $variantIds));
        $out = [];
        foreach ($this->inventory() as $row) {
            $vid = (string) ($row['variant_id'] ?? '');
            if ($vid !== '' && isset($want[$vid])) {
                $out[$row['variant_id']] = (int) ($row['stock'] ?? 0);
            }
        }

        return $out;
    }

    public function reportSale(array $sale): string
    {
        $location = $this->fulfillment();
        if ($location === '') {
            throw new RuntimeException('No StoqS fulfilment location configured (STOCKKEEPING_FULFILLMENT_LOCATION).');
        }

        $items = [];
        foreach ($sale['items'] ?? [] as $line) {
            $item = [
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_price' => (int) ($line['unit_price'] ?? 0),
            ];
            // Prefer the mapped StoqS variant id; fall back to barcode (sku);
            // fall back to local variant id so items aren't dropped if both are missing.
            if (! empty($line['stockkeeping_variant_id'])) {
                $item['variant_id'] = (int) $line['stockkeeping_variant_id'];
            } elseif (! empty($line['sku'])) {
                $item['barcode'] = (string) $line['sku'];
            } elseif (! empty($line['product_variant_id'])) {
                $item['local_variant_id'] = (int) $line['product_variant_id'];
            } else {
                // Still include the item; StoqS may match by name/price or accept it.
            }
            $items[] = $item;
        }

        $payload = array_filter([
            'location' => $location,
            'external_ref' => $sale['order_number'] ?? null,
            'channel' => 'website',
            'payment_type' => 'online',
            'redeem_points' => (int) ($sale['redeem_points'] ?? 0) ?: null,
            'customer' => array_filter([
                'name' => $sale['customer']['name'] ?? null,
                'phone' => $sale['customer']['phone'] ?? null,
                'address' => $sale['customer']['address'] ?? null,
            ]),
            'items' => $items,
            'subtotal' => (int) ($sale['subtotal'] ?? 0),
            'shipping_cost' => (int) ($sale['shipping_cost'] ?? 0),
            'discount' => (int) ($sale['discount'] ?? 0),
            'total' => (int) ($sale['total'] ?? 0),
        ], fn ($v) => $v !== null && $v !== [] && $v !== 0);

        $resp = $this->request()->post('/api/v1/sales', $payload)->throw();

        // StoqS returns batch_id; keep it as the remote sale reference.
        return (string) $resp->json('batch_id', '');
    }

    public function findCustomer(string $phone): ?array
    {
        if (trim($phone) === '') {
            return null;
        }
        // Runs on page loads (account/CRM) — must never throw. Unknown customer
        // (404), a missing crm scope (403), or StoqS being unreachable all just
        // mean "no unified profile".
        try {
            $resp = $this->request()->get('/api/v1/customers', ['phone' => $phone]);

            return $resp->successful() ? $resp->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function adjustStock(array $items, string $reason = 'website_adjust', ?string $externalRef = null): bool
    {
        if ($items === []) {
            return false;
        }
        // stock-adjust targets a warehouse; skip when fulfilment is a shop.
        [$type, $id] = array_pad(explode(':', $this->fulfillment(), 2), 2, null);
        if ($type !== 'warehouse' || ! $id) {
            return false;
        }

        $resp = $this->request()->post('/api/v1/stock-adjust', array_filter([
            'warehouse_id' => (int) $id,
            'reason' => $reason,
            'external_ref' => $externalRef,
            'items' => array_values($items),
        ], fn ($v) => $v !== null));

        return $resp->successful();
    }

    /**
     * Pull collections/groups from StoqS.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collections(): array
    {
        $resp = $this->request()->get('/api/v1/collections')->throw();

        return $resp->json('collections', []);
    }

    public function reportIncome(array $income): string
    {
        // StoqS derives income from the sale itself (payment_type=online is carried
        // on /sales), so there is no separate income endpoint. This is a deliberate
        // no-op kept to satisfy the contract; the outbox marks the event delivered.
        return '';
    }

    public function upsertCustomer(array $customer): string
    {
        $payload = array_filter([
            'name' => $customer['name'] ?? null,
            'phone' => $customer['phone'] ?? null,
            'email' => $customer['email'] ?? null,
            'address' => $customer['address'] ?? null,
        ]);

        if (($payload['phone'] ?? '') === '' && ($payload['name'] ?? '') === '') {
            return '';
        }

        $resp = $this->request()->post('/api/v1/customers', $payload);

        // If the key lacks crm:write, the sale's inline customer upsert still
        // covers CRM — treat as a soft success so the outbox doesn't wedge.
        if ($resp->status() === 403) {
            return '';
        }

        return (string) $resp->throw()->json('id', '');
    }
}
