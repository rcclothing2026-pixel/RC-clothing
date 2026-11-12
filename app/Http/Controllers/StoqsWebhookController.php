<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\CatalogImporter;
use App\Services\StockKeeping\StockKeepingClient;
use App\Services\StockKeeping\StoqsPromoSource;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    private string $eventId = '';

    private ?Carbon $at = null;

    /** What this event changed, for the out-of-order guard (see inOrder()). */
    private array $subjects = [];

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('stockkeeping.webhook_secret');
        $raw = $request->getContent();
        [$this->eventId, $this->at, $this->subjects] = ['', null, []];   // the route keeps one controller instance

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

        // StoqS «ارسال پیام آزمایشی»: a signed ping proves URL + secret; nothing else runs.
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true, 'site' => 'racketclub']);
        }

        // Echo prevention: if the webhook carries source_instance_id matching our
        // own, it was caused by this Chiiaco instance — skip to avoid loops.
        $sourceInstance = (string) ($body['source_instance_id'] ?? '');
        $myInstance = (string) config('stockkeeping.instance_id', '');
        if ($sourceInstance !== '' && $sourceInstance === $myInstance) {
            return response('ok (own echo skipped)', 200);
        }

        try {
            $this->at = ! empty($body['occurred_at']) ? Carbon::parse((string) $body['occurred_at'])->utc() : null;
        } catch (\Throwable) {
            $this->at = null;
        }
        // Replays: StoqS sends the same event_id on every retry of one event. The id is
        // claimed before anything is applied, so two overlapping copies cannot both run.
        $this->eventId = mb_substr((string) ($body['event_id'] ?? $request->header('X-Stoqs-Event-Id', '')), 0, 64);
        if (! $this->claim()) {
            return response('ok (duplicate skipped)', 200);
        }

        try {
            if ($event === 'stock.changed') {
                $this->applyStockChanged($data);
            } elseif (in_array($event, ['product.published', 'product.unpublished'], true)
                && ! empty($data['product_id']) && ! $this->inOrder('p:'.$data['product_id'])) {
                // An older catalog event than one already applied to this product: skipped.
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
            } elseif ($event === 'product.content_changed') {
                $this->applyContentChanged($data);
            } elseif ($event === 'promotions.source_changed') {
                // Two-way site manager switch: applied at once when its seq is newer, then acknowledged.
                $this->applySourceChanged($data);
            } elseif (in_array($event, ['promotions.changed', 'outlet.changed'], true)
                || (in_array($event, ['discounts.changed', 'campaign.changed'], true) && StoqsPromotions::managed())) {
                // Promotions API v2: a thin «pull /promotions» signal (also how this site learns
                // StoqS took its promotions over or gave them back).
                $this->pullPromotions($event);
            }
            // discounts.changed / campaign.changed (while the shop runs its own promotions) and
            // unknown events: acknowledged (200).
            // sale.created is informational here: its per-item stock effect already
            // arrives as stock.changed events, so we don't double-apply it.
        } catch (\Throwable $e) {
            Log::warning('[stoqs-webhook] processing failed', ['event' => $event, 'error' => $e->getMessage()]);
            // Still 200 so StoqS doesn't hammer retries for a row we can't match;
            // the next pull will reconcile.
            $this->release();   // ... but a retry of this event may run again
            $failed = true;
        }
        if (empty($failed)) {
            $this->recordEvent();
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

    /**
     * product.content_changed: name / text / prices / photos changed in StoqS. data =
     * {product_id, fields[], product{full catalog payload}}. Only a product the shop
     * already has is updated (its sync mode and unsent shop edits are respected).
     */
    private function applyContentChanged(array $data): bool
    {
        $sp = (array) ($data['product'] ?? []);
        $skId = (string) ($sp['product_id'] ?? $data['product_id'] ?? '');
        if (! $sp || $skId === '' || ! $this->inOrder('p:'.$skId)) {
            return false;
        }
        $barcodes = collect($sp['variants'] ?? [])->map(fn ($v) => (string) ($v['barcode'] ?? ''))->filter()->all();
        $known = Product::where('stockkeeping_id', $skId)->exists()
            || ($barcodes && ProductVariant::whereIn('sku', $barcodes)->exists());
        if (! $known) {
            return false;
        }
        $product = app(CatalogImporter::class)->importProduct($sp + ['product_id' => $skId]);
        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_CONTENT_PULL, 'in', $skId, null,
            ['fields' => $data['fields'] ?? [], 'product_id' => $product->id],
            source: StockkeeepingLog::SOURCE_AUTO,
            summary: $product->name.' ← StoqS: '.implode('، ', array_map(fn ($f) => \App\Services\StockKeeping\StoqsContent::LABELS[$f] ?? $f, (array) ($data['fields'] ?? []))),
        );

        return true;
    }

    /**
     * promotions.changed / outlet.changed (and, while StoqS manages this site, discounts.changed /
     * campaign.changed): pull /promotions (If-None-Match), put this moment's window prices on the
     * products and ack the version. Returns whether a price changed.
     */
    private function pullPromotions(string $event): bool
    {
        $pull = StoqsPromotions::pull();
        $applied = StoqsPromotions::apply();
        StoqsPromotions::ack();
        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_WEBHOOK_PRODUCT, 'in', $event, null,
            ['status' => $pull['status'], 'managed' => $pull['managed'], 'version' => StoqsPromotions::version(), 'changed' => $applied['changed']],
            $pull['ok'] ? 'ok' : 'failed', error: $pull['message'] ?? null, source: StockkeeepingLog::SOURCE_AUTO,
            summary: 'تخفیف‌های StoqS — '.($pull['managed'] ? $applied['changed'].' محصول' : 'این سایت با خود فروشگاه'),
        );

        return $applied['changed'] > 0 || $pull['flipped'];
    }

    /**
     * promotions.source_changed {source, seq, changed_at, by, note, origin}: applied when seq is
     * greater than the stored one (a stale or replayed seq is a 200 no-op), `managed` flips the
     * way a pull-driven flip does, the change is logged, and StoqS is told
     * (POST /promotions/ack {source_seq, own_discounts}). Returns whether the storefront changed.
     */
    private function applySourceChanged(array $data): bool
    {
        $source = (string) ($data['source'] ?? '');
        $applied = StoqsPromoSource::apply(
            $source, (int) ($data['seq'] ?? 0), StoqsPromoSource::origin($data['origin'] ?? null),
            isset($data['by']) ? (string) $data['by'] : null, isset($data['note']) ? (string) $data['note'] : null,
            isset($data['changed_at']) ? (string) $data['changed_at'] : null,
        );
        if (! $applied) {
            return false;
        }
        if (StoqsPromotions::managed()) {
            StoqsPromotions::apply();
            StoqsPromotions::ack();
        }

        return true;
    }

    /** Claim this event id (unique with subject ''); false when another delivery has it. */
    private function claim(): bool
    {
        if ($this->eventId === '') {
            return true;
        }
        try {
            DB::table('stoqs_webhook_events')->insert(['event_id' => $this->eventId, 'subject' => '', 'occurred_at' => $this->at, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return false;
        } catch (\Throwable $e) {
            Log::warning('[stoqs-webhook] could not claim the event', ['error' => $e->getMessage()]);   // e.g. not migrated yet
        }

        return true;
    }

    private function release(): void
    {
        try {
            if ($this->eventId !== '') {
                DB::table('stoqs_webhook_events')->where('event_id', $this->eventId)->delete();
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Out-of-order delivery: false when StoqS already sent a newer event for $subject
     * («p:<product id>» for catalog events, «s:<barcode>» for absolute stock counts).
     */
    private function inOrder(string $subject): bool
    {
        if (! $this->at) {
            return true;
        }
        try {
            if (DB::table('stoqs_webhook_events')->where('subject', $subject)->where('occurred_at', '>', $this->at)->exists()) {
                return false;
            }
        } catch (\Throwable) {
            return true;
        }
        $this->subjects[] = $subject;

        return true;
    }

    /** Remember what this event changed, and when, for 8 days (the id was claimed already). */
    private function recordEvent(): void
    {
        $now = now();
        $id = $this->eventId !== '' ? $this->eventId : 'no-id-'.bin2hex(random_bytes(8));
        try {
            if ($this->subjects) {
                DB::table('stoqs_webhook_events')->insert(array_map(fn ($s) => [
                    'event_id' => $id, 'subject' => $s, 'occurred_at' => $this->at, 'created_at' => $now,
                ], array_values(array_unique($this->subjects))));
            }
            DB::table('stoqs_webhook_events')->where('created_at', '<', $now->copy()->subDays(8))->delete();
        } catch (\Throwable $e) {
            Log::warning('[stoqs-webhook] could not record the event', ['error' => $e->getMessage()]);
        }
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
        if (! $variant || $variant->product?->stoqs_sync_mode === 'ignore') {
            return; // not a product we publish (or set to «نادیده») — ignore
        }

        // Map the StoqS variant id if we don't have it yet.
        if (empty($variant->stockkeeping_variant_id) && ! empty($data['variant_id'])) {
            $variant->stockkeeping_variant_id = (int) $data['variant_id'];
        }

        $single = count((array) config('stockkeeping.locations', [])) <= 1;
        $hasNew = array_key_exists('new_stock', $data) && $data['new_stock'] !== null;
        $reason = (string) ($data['reason'] ?? '');

        if ($single && $hasNew && ! $this->inOrder('s:'.$barcode)) {
            return; // an older absolute count than one already applied
        }
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
