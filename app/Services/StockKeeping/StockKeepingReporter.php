<?php

namespace App\Services\StockKeeping;

use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\StockkeeepingLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates a paid order into outbox events (sale / income / customer) destined
 * for the Chiaco Stock-Keeping SaaS, then attempts to flush them. Anything that
 * fails stays in the outbox for a later retry (see flushPending()).
 */
class StockKeepingReporter
{
    public function __construct(private readonly StockKeepingClient $client) {}

    /**
     * Push a customer to stock-keeping CRM independently of an order (on signup
     * or profile update), so the CRM is populated even before the first purchase.
     */
    public function syncCustomer(User $user): void
    {
        if (! config('stockkeeping.enabled') || ! $user->phone) {
            return;
        }
        $this->enqueue(IntegrationEvent::TYPE_CUSTOMER_UPSERTED, [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'address' => $this->formatAddress($user->addresses->first()),
        ]);
        $this->flushPending();
    }

    /** Record outbox events for a paid order, then try to deliver them. */
    public function reportPaidOrder(Order $order): void
    {
        $order->loadMissing([
            'items.variant.product.bundleItems.variant.product',
            'user.addresses', 'user.orders',
        ]);

        // Record which StoqS location this order is fulfilled from (the stock is
        // taken out of it), so the order info/history can show it. The friendly
        // name comes from the cached /locations directory the pull job refreshes.
        $location = config('stockkeeping.fulfillment_location')
            ?: (config('stockkeeping.locations')[0] ?? null);
        if ($location) {
            $names = (array) Cache::get('stockkeeping:locations', []);
            $order->update([
                'stockkeeping_location' => $location,
                'stockkeeping_location_name' => $names[$location] ?? null,
            ]);
        }

        $customer = $this->customerProfile($order);

        $sale = $this->buildSalePayload($order, $customer);

        $income = [
            'order_number' => $order->number,
            'amount' => $order->total,
            'currency' => 'IRT',
            'gateway' => 'zarinpal',
            'ref_id' => optional($order->payment)->ref_id,
            'paid_at' => optional($order->paid_at)->toIso8601String(),
        ];

        $this->enqueue(IntegrationEvent::TYPE_CUSTOMER_UPSERTED, $customer);
        $this->enqueue(IntegrationEvent::TYPE_SALE_CREATED, $sale);
        $this->enqueue(IntegrationEvent::TYPE_INCOME_RECORDED, $income);

        $this->flushPending();
    }

    /**
     * Build the StoqS sale payload from the order's CURRENT state. Extracted so a
     * manual resend rebuilds it fresh (picking up a just-added barcode/StoqS id)
     * instead of re-sending the payload frozen at purchase time.
     *
     * Bundles report to StoqS as their children, not as one fake SKU: the
     * bundle's placeholder variant has no StoqS id, so a single-line report would
     * trigger sku_not_found. Each bundle order item emits one child line per
     * bundle_item, with the bundle's unit price split across children.
     */
    public function buildSalePayload(Order $order, ?array $customer = null): array
    {
        $order->loadMissing(['items.variant.product.bundleItems.variant.product', 'items.variant.product.variants']);

        return [
            'order_number' => $order->number,
            'placed_at' => optional($order->placed_at)->toIso8601String(),
            'paid_at' => optional($order->paid_at)->toIso8601String(),
            'subtotal' => $order->subtotal,
            'shipping_cost' => $order->shipping_cost,
            'discount' => $order->discount,
            'total' => $order->total,
            'currency' => 'IRT', // Toman
            'redeem_points' => (int) ($order->loyalty_points_used ?? 0),
            'customer' => $customer ?? $this->customerProfile($order),
            'items' => $order->items->flatMap(fn ($i) => $this->expandSaleItems($i))->values()->all(),
        ];
    }

    /**
     * Turn one OrderItem into one-or-many line items for the StoqS sale report.
     * Plain items pass through as a single row. Bundles expand into one row per
     * (bundle_item × order quantity slot): a bundle bought ×2 with children
     * [A ×1, B ×2] emits A qty 2, B qty 4. The bundle's line_total is split
     * across children proportionally to each child's catalogue price so revenue
     * attribution makes sense in StoqS reports.
     *
     * @return iterable<int,array<string,mixed>>
     */
    private function expandSaleItems(\App\Models\OrderItem $orderItem): iterable
    {
        $variant = $orderItem->variant;
        $product = $variant?->product;

        if (! $product?->is_bundle) {
            // Prefer this line's own StoqS mapping (order snapshot, then the live
            // variant). If it's STILL unmapped — e.g. the product was split into
            // per-colour variants in StoqS after this order was placed — fall back
            // to a sibling variant of the same product that IS mapped, preferring
            // one with the same colour. StoqS pools these under one item, so this
            // reports the sale correctly instead of failing the whole order.
            $skId = $orderItem->stockkeeping_variant_id ?: $variant?->stockkeeping_variant_id;
            $sku  = $orderItem->sku ?: $variant?->sku;
            $oColor = trim((string) $orderItem->color);
            if (! $skId && ! $sku) {
                // (a) A mapped sibling on the SAME product, preferring same colour.
                if ($product) {
                    $mapped = $product->variants->filter(fn ($s) => $s->stockkeeping_variant_id || $s->sku);
                    $sib = $mapped->first(fn ($s) => $oColor !== '' && trim((string) $s->color) === $oColor)
                        ?? $mapped->first();
                    $skId = $sib?->stockkeeping_variant_id;
                    $sku  = $sib?->sku;
                }
                // (b) Last resort: a mapped variant on ANY product with the same
                //     name (covers duplicate / re-imported product rows), preferring
                //     the same colour. Only fires when the line is otherwise unmapped.
                if (! $skId && ! $sku && trim((string) $orderItem->name) !== '') {
                    $q = \App\Models\ProductVariant::whereHas('product', fn ($p) => $p->where('name', $orderItem->name))
                        ->where(fn ($w) => $w->whereNotNull('sku')->orWhereNotNull('stockkeeping_variant_id'));
                    $g = ($oColor !== '' ? (clone $q)->where('color', $oColor)->first() : null) ?? $q->first();
                    $skId = $g?->stockkeeping_variant_id;
                    $sku  = $g?->sku;
                }
            }
            return [[
                'stockkeeping_variant_id' => $skId,
                'product_variant_id' => $orderItem->product_variant_id,
                'sku' => $sku,
                'name' => $orderItem->name,
                'size' => $orderItem->size,
                'color' => $orderItem->color,
                'unit_price' => $orderItem->unit_price,
                'quantity' => $orderItem->quantity,
                'line_total' => $orderItem->line_total,
            ]];
        }

        $bundleItems = $product->bundleItems;
        if ($bundleItems->isEmpty()) {
            // Misconfigured bundle (no children) — fall back to the parent row so
            // the sale isn't dropped; StoqS will reject it with sku_not_found,
            // which surfaces in the admin alert exactly like other unknown items.
            return [[
                'stockkeeping_variant_id' => $orderItem->stockkeeping_variant_id,
                'product_variant_id' => $orderItem->product_variant_id,
                'sku' => $orderItem->sku,
                'name' => $orderItem->name,
                'size' => $orderItem->size,
                'color' => $orderItem->color,
                'unit_price' => $orderItem->unit_price,
                'quantity' => $orderItem->quantity,
                'line_total' => $orderItem->line_total,
            ]];
        }

        // Weights from each child's catalogue price × per-bundle quantity. If all
        // weights are zero (free children), split the bundle total evenly.
        $weights = $bundleItems->map(fn ($bi) => max(0, (int) (($bi->variant?->price ?? $bi->variant?->product?->price ?? 0)) * (int) $bi->quantity));
        $weightTotal = (int) $weights->sum();
        $lineTotal = (int) $orderItem->line_total;

        $out = [];
        $allocated = 0;
        foreach ($bundleItems as $i => $bi) {
            $child = $bi->variant;
            if (! $child) {
                continue;
            }
            $childQty = (int) $orderItem->quantity * (int) $bi->quantity;
            // Last row absorbs the rounding remainder so the totals reconcile to the cent.
            if ($i === $bundleItems->count() - 1) {
                $share = $lineTotal - $allocated;
            } else {
                $share = $weightTotal > 0
                    ? (int) round($lineTotal * ($weights[$i] / $weightTotal))
                    : (int) intdiv($lineTotal, max(1, $bundleItems->count()));
                $allocated += $share;
            }
            $childProduct = $child->product;
            $out[] = [
                'stockkeeping_variant_id' => $child->stockkeeping_variant_id,
                'product_variant_id' => $child->id,
                'sku' => $child->sku,
                'name' => $childProduct?->name ?? $orderItem->name,
                'size' => $child->size,
                'color' => $child->color,
                'unit_price' => $childQty > 0 ? (int) round($share / $childQty) : 0,
                'quantity' => $childQty,
                'line_total' => $share,
            ];
        }

        return $out;
    }

    /**
     * Full customer/CRM profile to share with stock-keeping: identity, contact,
     * saved addresses, and lifetime value (order count + total spent).
     */
    private function customerProfile(Order $order): array
    {
        $user = $order->user;

        $paidStatuses = [
            Order::STATUS_PAID, Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED, Order::STATUS_DELIVERED,
        ];

        $paidOrders = $user
            ? $user->orders->whereIn('status', $paidStatuses)
            : collect();

        return [
            'external_user_id' => $order->user_id,
            'name' => $user?->name ?? $order->customer_name,
            'phone' => $user?->phone ?? $order->customer_phone,
            'email' => $user?->email,
            'member_since' => optional($user?->created_at)->toIso8601String(),
            'address' => $user
                ? $this->formatAddress($user->addresses->first())
                : $this->formatAddress($order->shipping_address),
            // Lifetime value for CRM segmentation.
            'lifetime' => [
                'orders_count' => $paidOrders->count(),
                'total_spent' => (int) $paidOrders->sum('total'),
                'currency' => 'IRT',
                'last_order_number' => $order->number,
            ],
        ];
    }

    private function formatAddress(null|\App\Models\Address|array $address): ?string
    {
        if (! $address) {
            return null;
        }

        $a = $address instanceof \App\Models\Address
            ? $address->toSnapshot()
            : $address;

        $parts = array_filter([
            $a['recipient_name'] ?? null,
            $a['province'] ?? null,
            $a['city'] ?? null,
            $a['line'] ?? null,
            isset($a['postal_code']) ? 'کدپستی: '.$a['postal_code'] : null,
        ]);

        return implode(' - ', $parts) ?: null;
    }

    private function enqueue(string $type, array $payload): IntegrationEvent
    {
        return IntegrationEvent::create([
            'type' => $type,
            'payload' => $payload,
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }

    /** Attempt delivery of due pending/failed events. Safe to call repeatedly. */
    public function flushPending(int $limit = 50): void
    {
        $events = IntegrationEvent::whereIn('status', ['pending', 'failed'])
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            try {
                $remoteId = match ($event->type) {
                    IntegrationEvent::TYPE_SALE_CREATED => $this->client->reportSale($event->payload),
                    IntegrationEvent::TYPE_INCOME_RECORDED => $this->client->reportIncome($event->payload),
                    IntegrationEvent::TYPE_CUSTOMER_UPSERTED => $this->client->upsertCustomer($event->payload),
                    default => null,
                };

                $event->update([
                    'status' => 'sent',
                    'remote_id' => $remoteId,
                    'processed_at' => now(),
                    'last_error' => null,
                ]);

                // Link the remote StoqS sale (batch_id) back onto the order so the
                // order record shows it was reported and which location it left.
                if ($event->type === IntegrationEvent::TYPE_SALE_CREATED
                    && $remoteId
                    && ! empty($event->payload['order_number'])) {
                    $order = Order::where('number', $event->payload['order_number'])
                        ->whereNull('stockkeeping_sale_id')
                        ->first();
                    if ($order) {
                        $order->update(['stockkeeping_sale_id' => $remoteId]);
                        StockkeeepingLog::record(
                            StockkeeepingLog::TYPE_SALE_PUSH,
                            'out',
                            $event->payload['order_number'],
                            $order->id,
                            ['items' => $event->payload['items'] ?? [], 'total' => $event->payload['total'] ?? null],
                            'ok',
                            $remoteId,
                        );
                    }
                }
            } catch (Throwable $e) {
                $attempts = $event->attempts + 1;
                $event->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'last_error' => $e->getMessage(),
                    // Exponential backoff, capped at ~30 min.
                    'available_at' => now()->addSeconds(min(1800, 30 * (2 ** $attempts))),
                ]);
                Log::warning('[stock-keeping] event delivery failed', [
                    'event_id' => $event->id, 'type' => $event->type, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
