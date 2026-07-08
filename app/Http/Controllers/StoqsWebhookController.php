<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\CatalogImporter;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives signed webhooks from StoqS (stock.changed / sale.created) and keeps
 * the local stock cache in lock-step with StoqS in near real time. The periodic
 * `stockkeeping:pull` command remains the authoritative reconciler; this just
 * lowers latency between a StoqS movement and the storefront reflecting it.
 *
 * Signature: X-Stoqs-Signature: sha256=<hmac_sha256(rawBody, webhook_secret)>.
 */
class StoqsWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('stockkeeping.webhook_secret');
        $raw = $request->getContent();

        // Reject anything we can't verify. A missing secret = integration not
        // configured for inbound, so refuse rather than trust blindly.
        if ($secret === '' || ! $this->signatureValid($request, $raw, $secret)) {
            return response('invalid signature', 401);
        }

        $body = json_decode($raw, true);
        if (! is_array($body)) {
            return response('bad json', 400);
        }

        $event = (string) ($body['event'] ?? '');
        $data = (array) ($body['data'] ?? []);

        // Echo prevention: if the webhook carries source_instance_id matching our
        // own, it was caused by this Chiiaco instance — skip to avoid loops.
        $sourceInstance = (string) ($body['source_instance_id'] ?? '');
        $myInstance = (string) config('stockkeeping.instance_id', '');
        if ($sourceInstance !== '' && $sourceInstance === $myInstance) {
            return response('ok (own echo skipped)', 200);
        }

        try {
            if ($event === 'stock.changed') {
                $this->applyStockChanged($data);
            } elseif ($event === 'product.published') {
                // Respect the "only in-stock" setting for real-time publishes too.
                if (config('stockkeeping.import_in_stock_only', true) && ! $this->productHasStock($data)) {
                    StockkeeepingLog::record(
                        StockkeeepingLog::TYPE_WEBHOOK_PRODUCT, 'in', (string) ($data['product_id'] ?? ''),
                        null, ['name' => $data['name'] ?? '?', 'skipped' => 'no_stock'],
                        source: StockkeeepingLog::SOURCE_AUTO,
                        summary: ($data['name'] ?? '?') . ' — بدون موجودی، رد شد',
                    );
                } else {
                    $product = app(CatalogImporter::class)->importProduct($data);
                    StockkeeepingLog::record(
                        StockkeeepingLog::TYPE_WEBHOOK_PRODUCT, 'in', (string) ($data['product_id'] ?? ''),
                        null, ['name' => $product->name, 'variants' => count($data['variants'] ?? [])],
                        source: StockkeeepingLog::SOURCE_AUTO,
                        summary: "{$product->name} — " . count($data['variants'] ?? []) . " تنوع",
                    );
                }
            } elseif ($event === 'product.unpublished') {
                if (! empty($data['product_id'])) {
                    Product::where('stockkeeping_id', (string) $data['product_id'])
                        ->update(['is_active' => false]);
                }
            }
            // sale.created is informational here: its per-item stock effect already
            // arrives as stock.changed events, so we don't double-apply it.
        } catch (\Throwable $e) {
            Log::warning('[stoqs-webhook] processing failed', ['event' => $event, 'error' => $e->getMessage()]);
            // Still 200 so StoqS doesn't hammer retries for a row we can't match;
            // the next pull will reconcile.
        }

        return response('ok', 200);
    }

    /**
     * Whether a published product currently has stock. Uses the payload's stock
     * fields when present; if the payload is silent, asks StoqS for the variants'
     * stock. On uncertainty it returns true (never silently drops a product — the
     * next pull reconciles).
     *
     * @param  array<string, mixed>  $data
     */
    private function productHasStock(array $data): bool
    {
        $payloadHadStock = isset($data['stock']);
        if ($payloadHadStock && (int) $data['stock'] > 0) {
            return true;
        }

        $variantIds = [];
        foreach ($data['variants'] ?? [] as $v) {
            if (isset($v['stock'])) {
                $payloadHadStock = true;
                if ((int) $v['stock'] > 0) {
                    return true;
                }
            }
            if (! empty($v['variant_id'])) {
                $variantIds[] = (int) $v['variant_id'];
            }
        }

        if ($payloadHadStock) {
            return false; // payload reported stock and it was all zero
        }

        if ($variantIds !== []) {
            try {
                foreach (app(StockKeepingClient::class)->fetchStock($variantIds) as $qty) {
                    if ((int) $qty > 0) {
                        return true;
                    }
                }

                return false;
            } catch (\Throwable $e) {
                return true; // can't determine → import, don't drop
            }
        }

        return true;
    }

    private function signatureValid(Request $request, string $raw, string $secret): bool
    {
        $header = (string) $request->header('X-Stoqs-Signature', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }
        $expected = hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, substr($header, 7));
    }

    private function applyStockChanged(array $data): void
    {
        $barcode = (string) ($data['barcode'] ?? '');
        if ($barcode === '') {
            return;
        }

        $variant = ProductVariant::where('sku', $barcode)->first();
        if (! $variant) {
            return; // not a product we publish — ignore
        }

        // Map the StoqS variant id if we don't have it yet.
        if (empty($variant->stockkeeping_variant_id) && ! empty($data['variant_id'])) {
            $variant->stockkeeping_variant_id = (int) $data['variant_id'];
        }

        $single = count((array) config('stockkeeping.locations', [])) <= 1;
        $hasNew = array_key_exists('new_stock', $data) && $data['new_stock'] !== null;
        $reason = (string) ($data['reason'] ?? '');

        if ($single && $hasNew) {
            // Single-location mirror: new_stock IS our aggregate — set it (idempotent,
            // echo-safe even for our own reported sale).
            $variant->stock_qty = max(0, (int) $data['new_stock']);
        } elseif (array_key_exists('delta', $data) && $data['delta'] !== null) {
            // Multi-location: a per-location absolute can't be our aggregate, so apply
            // the delta — EXCEPT our own website sales (reason sale_api), which we
            // already decremented locally at checkout. The next pull reconciles exactly.
            if ($reason === 'sale_api') {
                $variant->save(); // persist any variant-id mapping only
                return;
            }
            $variant->stock_qty = max(0, (int) $variant->stock_qty + (int) $data['delta']);
        }

        $variant->save();

        Product::where('id', $variant->product_id)->update(['stock_synced_at' => now()]);

        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_WEBHOOK_IN,
            'in',
            $barcode,
            null,
            ['barcode' => $barcode, 'reason' => $reason, 'delta' => $data['delta'] ?? null, 'new_stock' => $data['new_stock'] ?? null],
        );
    }
}
