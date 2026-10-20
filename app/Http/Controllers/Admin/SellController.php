<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellLink;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «فروش سریع» — the phone-first admin screen (installable, /admin/sell) where an
 * admin builds a customer's cart while chatting on Instagram and gets a short
 * /b/{code} purchase link. It only prepares a cart: price, stock and payment
 * all stay with the normal cart + checkout when the customer opens the link.
 */
class SellController extends Controller
{
    public function index(): View
    {
        return view('admin.sell.index');
    }

    /** Products (with buyable variants) by name or barcode/SKU; newest first when empty. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $products = Product::active()
            // Bought on an outside site — the cart refuses these, so don't offer them.
            ->where(fn ($w) => $w->where('external_enabled', false)->orWhereNull('external_url')->orWhere('external_url', ''))
            ->with(['images', 'variants' => fn ($v) => $v->where('is_active', true)->orderBy('id')])
            ->whereHas('variants', fn ($v) => $v->where('is_active', true))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$q}%"));
                });
            })
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json([
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'image' => $p->primary_image_url,
                'price' => Money::toman($p->variants->first()?->effectivePrice() ?? $p->price),
                'stock' => $p->variants->sum(fn (ProductVariant $v) => max(0, $v->availableQty())),
                'variants' => $p->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'color' => $v->color,
                    'color_hex' => $v->color_hex,
                    'size' => $v->size,
                    'sku' => $v->sku,
                    'stock' => max(0, $v->availableQty()),
                    'price' => $v->effectivePrice(),
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'coupon' => ['nullable', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:100'],
            'hours' => ['required', 'integer', 'in:24,72,168'],
        ]);

        $items = collect($data['items'])
            ->groupBy('variant_id')
            ->map(fn ($rows, $id) => ['variant_id' => (int) $id, 'qty' => min(20, (int) $rows->sum('qty'))])
            ->values();

        $variants = ProductVariant::with('product')->whereIn('id', $items->pluck('variant_id'))->get()->keyBy('id');
        $subtotal = $items->sum(fn ($i) => $variants[$i['variant_id']]->effectivePrice() * $i['qty']);

        $coupon = null;
        if (filled($data['coupon'] ?? null)) {
            $coupon = Coupon::findByCode($data['coupon']);
            if (! $coupon) {
                return response()->json(['message' => 'کد تخفیف پیدا نشد.'], 422);
            }
            if ($reason = $coupon->reasonInvalidFor($subtotal)) {
                return response()->json(['message' => $reason], 422);
            }
        }

        $link = SellLink::create([
            'code' => SellLink::newCode(),
            'created_by' => $request->user()->id,
            'items' => $items->all(),
            'coupon_code' => $coupon?->code,
            'customer_handle' => filled($data['handle'] ?? null) ? trim($data['handle']) : null,
            'expires_at' => now()->addHours((int) $data['hours']),
        ]);

        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;

        return response()->json([
            'code' => $link->code,
            'url' => $link->url(),
            'units' => $link->unitCount(),
            'total' => Money::toman(max(0, $subtotal - $discount)),
            'names' => $variants->map(fn ($v) => $v->product?->name)->filter()->unique()->values(),
        ]);
    }

    /** Recent links with their state, newest first. */
    public function links(): JsonResponse
    {
        $labels = ['sent' => 'ارسال شده', 'opened' => 'باز شد', 'ordered' => 'ثبت سفارش', 'paid' => 'پرداخت شد', 'expired' => 'منقضی'];

        $links = SellLink::with('order')->latest('id')->limit(50)->get();

        return response()->json([
            'links' => $links->map(fn (SellLink $l) => [
                'code' => $l->code,
                'url' => $l->url(),
                'handle' => $l->customer_handle,
                'units' => $l->unitCount(),
                'status' => $l->status(),
                'label' => $labels[$l->status()],
                'when' => Money::toPersianDigits((string) $l->created_at?->diffForHumans()),
                'order' => $l->order?->number,
            ]),
            'today' => [
                'created' => SellLink::whereDate('created_at', today())->count(),
                'opened' => SellLink::whereDate('created_at', today())->whereNotNull('opened_at')->count(),
                'ordered' => SellLink::whereDate('created_at', today())->whereNotNull('order_id')->count(),
            ],
        ]);
    }
}
