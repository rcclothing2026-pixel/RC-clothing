<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\SellLink;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Purchase links for the Instagram DM responder (NJ Focus): the same /b/{code}
 * link an admin builds on /admin/sell (SellController::store), made from a
 * chat instead. It only prepares a cart — price, stock and payment stay with
 * the normal cart + checkout when the customer opens the link. Guarded by
 * VerifyIgCatalogKey (routes/api.php).
 *
 * Stricter than the admin screen, because nobody looks at the link before it
 * goes out: at most 5 lines × 5 units, no coupon, and every line must be
 * buyable right now (active size of an active product that is sold here, not
 * a «Buy from Seller» one; enough stock; a real price) — otherwise a 422
 * naming the variant, so the responder can say so.
 */
class IgSellLinkController extends Controller
{
    private const MAX_QTY = 5;

    private const SOURCE = 'NJ Focus';

    public function store(Request $request): JsonResponse
    {
        $unknown = array_diff(array_map('strval', array_keys($request->all())), ['items', 'handle', 'hours']);
        if ($unknown !== []) {
            throw ValidationException::withMessages(
                collect($unknown)->mapWithKeys(fn ($k) => [$k => ["The {$k} field is not allowed."]])->all(),
            );
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'items.*' => ['required', 'array:variant_id,qty'],
            'items.*.variant_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QTY],
            'handle' => ['nullable', 'string', 'max:60'],
            'hours' => ['sometimes', 'nullable', 'integer', 'in:24,72,168'],
        ]);

        // Same variant twice → one line, quantities added (still at most 5).
        $items = collect($data['items'])
            ->groupBy(fn ($i) => (int) $i['variant_id'])
            ->map(fn ($rows, $id) => ['variant_id' => (int) $id, 'qty' => min(self::MAX_QTY, (int) $rows->sum('qty'))])
            ->values();

        $variants = ProductVariant::with('product')->whereIn('id', $items->pluck('variant_id'))->get()->keyBy('id');

        foreach ($items as $item) {
            if ($reason = $this->whyNot($variants->get($item['variant_id']), $item['qty'])) {
                return response()->json(['message' => $reason, 'variant_id' => $item['variant_id']], 422);
            }
        }

        $handle = ltrim(trim((string) ($data['handle'] ?? '')), '@');

        $link = SellLink::create([
            'code' => SellLink::newCode(),
            'created_by' => null,
            'items' => $items->all(),
            'coupon_code' => null,
            'customer_handle' => $handle !== '' ? '@'.$handle.' · '.self::SOURCE : self::SOURCE,
            'expires_at' => now()->addHours((int) ($data['hours'] ?? 72)),
        ]);

        $lines = $items->map(function ($i) use ($variants) {
            $v = $variants[$i['variant_id']];

            return [
                'variant_id' => $v->id,
                'product_id' => $v->product_id,
                // Raw column, as the catalogue row: no locale accessor in between.
                'name' => $v->product->getAttributes()['name'] ?? null,
                'label' => implode(' / ', array_filter([$v->color, $v->size])) ?: null,
                'size' => $v->size,
                'color' => $v->color,
                'qty' => $i['qty'],
                'price_toman' => $v->effectivePrice(),
            ];
        });

        return response()->json([
            'code' => $link->code,
            'url' => $this->linkUrl($link),
            'expires_at' => $link->expires_at->toIso8601String(),
            'units' => $link->unitCount(),
            'total_toman' => (int) $lines->sum(fn ($l) => $l['price_toman'] * $l['qty']),
            'items' => $lines->values(),
        ], 201);
    }

    /** Where a link stands: sent → opened → ordered → paid (or expired). */
    public function show(string $code): JsonResponse
    {
        $link = SellLink::with('order')->where('code', strtoupper($code))->first();
        abort_unless($link !== null, 404);

        return response()->json([
            'code' => $link->code,
            'url' => $this->linkUrl($link),
            'status' => $link->status(),
            'opened_at' => $link->opened_at?->toIso8601String(),
            'open_count' => (int) $link->open_count,
            'order_number' => $link->order?->number,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'created_at' => $link->created_at?->toIso8601String(),
        ]);
    }

    /**
     * $link->url() on the storefront host: from APP_URL, not the caller's Host
     * header (as the catalogue's URLs), so a call that reaches the app by
     * another name still gets the link a customer can open.
     */
    private function linkUrl(SellLink $link): string
    {
        return rtrim((string) config('app.url'), '/').route('sell.show', $link->code, false);
    }

    /** Persian reason this line can't go in a link right now, or null when it can. */
    private function whyNot(?ProductVariant $v, int $qty): ?string
    {
        if (! $v || ! $v->product) {
            return 'این کالا پیدا نشد.';
        }
        if (! $v->is_active || ! $v->product->is_active) {
            return 'این کالا الان فروخته نمی‌شود.';
        }
        if ($v->product->isExternal()) {
            // «Buy from Seller»: the cart refuses it (CartController::add), so must a link.
            return 'این محصول از سایت فروشنده خریداری می‌شود.';
        }
        $available = max(0, $v->availableQty());
        if ($available < 1) {
            return 'این کالا تمام شده است.';
        }
        if ($available < $qty) {
            return 'از این کالا فقط '.Money::toPersianDigits((string) $available).' عدد موجود است.';
        }
        if ($v->effectivePrice() <= 0) {
            return 'قیمت این کالا ثبت نشده است.';
        }

        return null;
    }
}
