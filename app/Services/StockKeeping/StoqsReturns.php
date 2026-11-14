<?php

namespace App\Services\StockKeeping;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\StockkeeepingLog;

/**
 * StoqS 5.2.133: refunds and cancels through the returns API (POST /sales/returns) when StoqS
 * advertises `sale_returns` in its hello answer (StoqsSites::supports). StoqS puts the stock back
 * itself (warehouse rows, or the shop's on-hand through the reversal row), so on this path the
 * shop never also calls stock-adjust for the same items. The shop's own local stock is still
 * restocked by the callers, as before.
 *
 * sale_ref = the order number: exactly the external_ref StockKeepingReporter/HttpStockKeepingClient
 * report the sale with.
 */
class StoqsReturns
{
    /** A received return: kind items, one line per returned item (a kit = ONE line). */
    public static function receive(ReturnRequest $return): void
    {
        $lines = [];
        foreach ((array) $return->items as $it) {
            $qty = (int) ($it['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $variant = ! empty($it['product_variant_id']) ? ProductVariant::with('product')->find($it['product_variant_id']) : null;
            array_push($lines, ...StoqsKits::returnLines($variant, $qty,
                ! empty($it['stockkeeping_variant_id']) ? (int) $it['stockkeeping_variant_id'] : null, ! empty($it['sku']) ? (string) $it['sku'] : null));
        }
        $order = $return->order;
        if ($lines === [] || ! $order) {
            return;
        }
        $payload = [
            'sale_ref' => (string) $order->number,
            'return_id' => 'RET-'.$return->id,
            'kind' => 'items',
            'lines' => $lines,
            'reason' => 'refund',
        ];
        // The return model has no refund amount field, so `refund_amount` is omitted.
        self::send($payload, StockkeeepingLog::TYPE_SALE_RETURN, $payload['return_id'], (int) $order->id);
    }

    /** A cancelled order: kind full, no lines. */
    public static function cancel(Order $order): void
    {
        $payload = [
            'sale_ref' => (string) $order->number,
            'return_id' => 'CANCEL-'.$order->number,
            'kind' => 'full',
            'reason' => 'cancel',
        ];
        self::send($payload, StockkeeepingLog::TYPE_SALE_RETURN, $payload['return_id'], (int) $order->id);
    }

    /** One call, logged. 404/409/422 are answers, not failures to retry: logged, done. Network/5xx throw inside: logged failed. */
    public static function send(array $payload, string $logType, string $ref, ?int $orderId): void
    {
        try {
            $res = app(StockKeepingClient::class)->returnSale($payload);
        } catch (\Throwable $e) {
            report($e);
            StockkeeepingLog::record($logType, 'out', $ref, $orderId, $payload, 'failed', null, $e->getMessage());

            return;
        }
        if ($res === null) {
            StockkeeepingLog::record($logType, 'out', $ref, $orderId, $payload, 'failed', null, 'StoqS پاسخی نداد');

            return;
        }
        if (($res['ok'] ?? false) === true) {
            StockkeeepingLog::record($logType, 'out', $ref, $orderId, $payload + ['answer' => $res]);

            return;
        }
        $err = (array) ($res['error'] ?? []);
        StockkeeepingLog::record($logType, 'out', $ref, $orderId, $payload + ['answer' => $res], 'failed', null,
            trim((string) ($err['code'] ?? 'error').': '.(string) ($err['message'] ?? '')));
    }
}
