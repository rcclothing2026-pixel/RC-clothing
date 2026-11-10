<?php

namespace App\Services\StockKeeping;

use App\Models\Coupon;
use App\Models\IntegrationEvent;
use App\Models\StockkeeepingLog;

/**
 * The shop's own coupons registered in StoqS (POST/PUT/DELETE /discounts/codes,
 * scope discounts:write), so the owner's discount report and till know them.
 * StoqS lets a key manage only the codes it created; coupons.stoqs_code_id keeps
 * the id. A code StoqS refuses (duplicate, invalid) is noted on the coupon and
 * not retried. Each shop registers only its own coupons, with channel web.
 */
class StoqsCodes
{
    public const TYPE = 'coupon.sync';

    /** Fields whose change is worth telling StoqS (not used_count). */
    public const FIELDS = ['code', 'description', 'type', 'value', 'min_subtotal', 'max_subtotal', 'max_discount',
        'usage_limit', 'per_user_limit', 'first_order_only', 'starts_at', 'expires_at', 'is_active', 'exclusive'];

    public static function enqueue(int $couponId, ?int $codeId = null, bool $delete = false): void
    {
        if (! config('stockkeeping.enabled')) {
            return;
        }
        $ev = IntegrationEvent::create([
            'type' => self::TYPE, 'status' => 'pending', 'available_at' => now(),
            'payload' => array_filter(['coupon_id' => $couponId, 'code_id' => $codeId, 'delete' => $delete ?: null]),
        ]);
        app(StockKeepingReporter::class)->deliver($ev);
    }

    /** Outbox handler. Throws StoqsApiError to retry; returns a log line. */
    public static function deliver(IntegrationEvent $ev): string
    {
        $pl = (array) $ev->payload;
        if (! empty($pl['delete'])) {
            if (! empty($pl['code_id'])) {
                try {
                    StoqsApi::call('DELETE', 'discounts/codes/'.(int) $pl['code_id']);
                } catch (StoqsApiError $e) {
                    if (! in_array($e->status, [403, 404], true) || $e->missingScope()) {
                        throw $e;
                    }
                }
            }

            return 'کد حذف شد';
        }
        $c = Coupon::find((int) ($pl['coupon_id'] ?? 0));
        if (! $c) {
            return '';
        }
        $body = [
            'code' => $c->code,
            'label' => $c->description ?: $c->code,
            'type' => match ($c->type) { Coupon::TYPE_FIXED => 'toman', Coupon::TYPE_FREE_SHIPPING => 'free_shipping', default => 'percent' },
            'value' => (int) $c->value,
            'min_subtotal' => $c->min_subtotal ?: null,
            'max_subtotal' => $c->max_subtotal,
            'max_discount' => $c->max_discount ?: null,
            'usage_limit' => $c->usage_limit,
            'per_customer_limit' => $c->per_user_limit,
            'first_order_only' => (bool) $c->first_order_only,
            'individual_use' => (bool) $c->exclusive,
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->expires_at?->toIso8601String(),
            'channels' => ['web'],
            'external_id' => (string) $c->id,
            'enabled' => (bool) $c->is_active,
        ];
        try {
            try {
                $r = $c->stoqs_code_id
                    ? StoqsApi::call('PUT', 'discounts/codes/'.(int) $c->stoqs_code_id, $body)
                    : StoqsApi::call('POST', 'discounts/codes', $body);
            } catch (StoqsApiError $e) {
                if ($e->status !== 404 || ! $c->stoqs_code_id) {
                    throw $e;
                }
                $r = StoqsApi::call('POST', 'discounts/codes', $body);   // removed in StoqS: register again
            }
        } catch (StoqsApiError $e) {
            if (! in_array($e->errorCode, ['duplicate_code', 'invalid', 'not_owner'], true)) {
                throw $e;
            }
            $c->forceFill(['stoqs_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();
            StockkeeepingLog::record(StockkeeepingLog::TYPE_DISCOUNTS, 'out', $c->code, null, ['code' => $e->errorCode], 'failed',
                error: $e->getMessage(), source: StockkeeepingLog::SOURCE_AUTO, summary: 'کد '.$c->code.' در StoqS ثبت نشد: '.$e->getMessage());

            return 'refused: '.$e->errorCode;
        }
        $c->forceFill(['stoqs_code_id' => (int) ($r['code']['id'] ?? $c->stoqs_code_id) ?: null, 'stoqs_error' => null])->saveQuietly();

        return 'کد '.$c->code.' در StoqS ثبت شد';
    }
}
