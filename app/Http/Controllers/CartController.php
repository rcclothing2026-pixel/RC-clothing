<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class CartController extends Controller
{
    public function __construct(private readonly Cart $cart) {}

    public function index(): View
    {
        $minFreeShipping = ShippingMethod::whereNotNull('free_over')->where('free_over', '>', 0)->min('free_over');

        return view('cart.index', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'coupon' => $this->cart->coupon(),
            'discount' => $this->cart->discount(),
            'ruleDiscounts' => $this->cart->ruleDiscountBreakdown(),
            'surcharge' => $this->cart->surcharge(),
            'giftCard' => $this->cart->giftCard(),
            'minFreeShipping' => $minFreeShipping ?: null,
            'recommended' => Product::active()->featured()->take(4)->get(),
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);
        $result = $this->cart->applyCoupon($data['code']);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back()->with('success', 'کد تخفیف حذف شد.');
    }

    public function applyGift(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);
        $result = $this->cart->applyGiftCard($data['code']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function removeGift(): RedirectResponse
    {
        $this->cart->removeGiftCard();

        return back()->with('success', 'کارت هدیه حذف شد.');
    }

    /** Restore a saved cart from an abandoned-cart recovery link. */
    public function restore(Request $request, string $token): RedirectResponse
    {
        $saved = \App\Models\SavedCart::where('token', $token)->first();
        if ($saved && $saved->user_id === $request->user()->id) {
            $this->cart->restoreItems((array) $saved->items);

            return redirect()->route('cart.index')->with('success', 'سبد خرید شما بازیابی شد.');
        }

        return redirect()->route('cart.index');
    }

    public function add(Request $request): mixed
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);

        // External-purchase products are sold on the retailer's site, not here —
        // reject any add-to-cart attempt (root-cause guard for every POST path:
        // product page, card quick-add, sticky bar).
        if ($variant->product && $variant->product->isExternal()) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'این محصول از سایت فروشنده خریداری می‌شود.'], 422);
            }
            return back()->with('error', 'این محصول از سایت فروشنده خریداری می‌شود.');
        }

        if (! $variant->inStock()) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'این مورد موجود نیست.'], 422);
            }
            return back()->with('error', 'این مورد موجود نیست.');
        }

        $this->cart->add($variant->id, $data['quantity'] ?? 1);

        // AJAX path: return enough state for the mini-cart drawer to render
        // without a second roundtrip. Form-only fallback (no JS) still gets
        // the original redirect.
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'محصول به سبد خرید اضافه شد.',
                'cart' => $this->miniCartPayload($variant),
            ]);
        }

        return back()->with('success', 'محصول به سبد خرید اضافه شد.');
    }

    /**
     * Snapshot used by the mini-cart drawer: count + subtotal + last-added
     * highlight + the most-recent 4 line items. Kept on the controller so
     * the Cart service stays free of view concerns.
     */
    private function miniCartPayload(ProductVariant $justAdded): array
    {
        $lines = $this->cart->lines();
        $top = $lines->take(4)->map(function ($l) {
            $variant = $l['variant'];
            return [
                'name' => $variant->product?->name,
                'variant_label' => trim(($variant->color ?? '').' '.($variant->size ?? '')),
                'quantity' => $l['quantity'],
                'line_total' => \App\Support\Money::toman($l['line_total']),
                'image' => $variant->product?->primary_image_url,
                'url' => $variant->product ? route('product.show', $variant->product) : null,
            ];
        })->values();

        return [
            'count' => $this->cart->count(),
            'subtotal' => \App\Support\Money::toman($this->cart->subtotal()),
            'subtotal_raw' => (int) $this->cart->subtotal(),
            'cart_url' => route('cart.index'),
            'checkout_url' => route('checkout.index'),
            'just_added' => [
                'name' => $justAdded->product?->name,
                'image' => $justAdded->product?->primary_image_url,
                'variant_label' => trim(($justAdded->color ?? '').' '.($justAdded->size ?? '')),
            ],
            'items' => $top,
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $this->cart->update($data['variant_id'], $data['quantity']);

        return back()->with('success', 'سبد خرید به‌روزرسانی شد.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'integer']]);
        $this->cart->remove($data['variant_id']);

        return back()->with('success', 'محصول از سبد خرید حذف شد.');
    }
}
