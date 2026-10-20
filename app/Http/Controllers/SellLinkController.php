<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\SellLink;
use App\Services\Cart\Cart;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /b/{code} — the purchase link an admin sent on Instagram. Puts the link's
 * items into the visitor's normal session cart (setting each quantity, so a
 * second tap never doubles it; anything already in the cart stays), applies the
 * link's coupon, and shows a short «سبد شما آماده است» page. From there it's the
 * ordinary checkout — no new pricing, stock or payment logic.
 */
class SellLinkController extends Controller
{
    /** Link-preview fetchers (Instagram/Telegram/WhatsApp cards) must not count as opens or fill a cart. */
    private const PREVIEW_AGENTS = '/facebookexternalhit|facebookcatalog|meta-externalagent|Instagram.*Preview|TelegramBot|WhatsApp|Twitterbot|Slackbot|bot\b|crawler|spider/i';

    public function __construct(private readonly Cart $cart) {}

    public function show(Request $request, string $code): Response
    {
        $link = SellLink::where('code', strtoupper($code))->first();
        abort_unless($link, 404);

        $variants = ProductVariant::with(['product.images'])
            ->whereIn('id', collect($link->items)->pluck('variant_id'))
            ->get()->keyBy('id');

        if ($link->isExpired()) {
            return $this->noStore(response()->view('sell.expired', [], 410));
        }

        $preview = (bool) preg_match(self::PREVIEW_AGENTS, (string) $request->userAgent());
        $soldOut = [];

        if (! $preview) {
            foreach ($link->items as $item) {
                $variant = $variants->get($item['variant_id']);
                if (! $variant || ! $variant->product?->is_active || $variant->product->isExternal() || ! $variant->inStock()) {
                    $soldOut[] = trim(($variant?->product?->name ?? __('An item')).' '.($variant?->color ?? '').' '.($variant?->size ?? ''));
                    continue;
                }
                $this->cart->update($variant->id, min((int) $item['qty'], $variant->availableQty()));
            }

            if ($link->coupon_code && ! $this->cart->isEmpty()) {
                $this->cart->applyCoupon($link->coupon_code);
            }

            $request->session()->put('sell_link_id', $link->id);
            if (! $request->user()) {
                // After the inline SMS login, land straight on checkout.
                $request->session()->put('url.intended', route('checkout.index'));
            }

            $link->forceFill([
                'opened_at' => $link->opened_at ?? now(),
                'open_count' => $link->open_count + 1,
            ])->save();
        }

        return $this->noStore(response()->view('sell.show', [
            'link' => $link,
            'linkVariants' => $variants,
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'discount' => $this->cart->combinedDiscount(),
            'total' => $this->cart->total(),
            'coupon' => $this->cart->coupon(),
            'soldOut' => $soldOut,
        ]));
    }

    /** Per-visitor page: never let the server's page cache store it. */
    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');

        return $response;
    }
}
