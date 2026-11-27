<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\Cart\Cart;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\StockKeeping\StockKeepingClient;
use App\Services\StockKeeping\StockKeepingReporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly PaymentGatewayFactory $gateways,
    ) {}

    public function index(Request $request, StockKeepingClient $stockKeeping): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'سبد خرید شما خالی است.');
        }

        // Check discount rule blocks before showing checkout.
        if ($this->cart->hasRuleBlocks()) {
            $messages = $this->cart->ruleBlockMessages();
            return redirect()->route('cart.index')->with('error', implode(' ', $messages));
        }

        // Loyalty: the customer's StoqS points + redemption rate, if available.
        $loyalty = null;
        $phone = $request->user()->phone;
        if ($phone && config('stockkeeping.enabled')) {
            $crm = $stockKeeping->findCustomer($phone);
            if ($crm && ($crm['ok'] ?? false) && ! empty($crm['loyalty'])) {
                $loyalty = [
                    'points' => (int) ($crm['customer']['loyalty_points'] ?? 0),
                    'toman_per_point' => (float) ($crm['loyalty']['toman_per_point'] ?? 0),
                    'min_redeem' => (int) ($crm['loyalty']['min_redeem_points'] ?? 0),
                    'blocked' => $this->pointsBlocked($crm['loyalty']),
                ];
            }
        }

        $user = $request->user();
        $defaultAddr = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first();
        // Returning customer: pre-pick last order's shipping + payment choice.
        $lastOrder = $user->orders()->latest('id')->with('payment')->first();

        return view('checkout.index', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'coupon' => $this->cart->coupon(),
            'discountNotice' => $this->cart->discountNotice(),
            'discount' => $this->cart->discount(),
            'combinedDiscount' => $this->cart->combinedDiscount(),
            'ruleDiscounts' => $this->cart->ruleDiscountBreakdown(),
            'surcharge' => $this->cart->surcharge(),
            'freeShipping' => $this->cart->hasFreeShipping(),
            'loyalty' => $loyalty,
            'giftCard' => $this->cart->giftCard(),
            'addresses' => $user->addresses()->get(),
            'shippingMethods' => ShippingMethod::where('is_active', true)->orderBy('position')->get(),
            'paymentMethods' => PaymentMethod::active()->get()->filter(fn ($m) => $this->gateways->usable($m))->values(),
            'user' => $user,
            'defaultAddress' => $defaultAddr?->line ?? '',
            'defaultProvince' => $defaultAddr?->province ?? '',
            'defaultCity' => $defaultAddr?->city ?? '',
            'lastShippingId' => $lastOrder?->shipping_method_id ? (int) $lastOrder->shipping_method_id : null,
            'lastPaymentKey' => $lastOrder?->payment?->gateway,
        ]);
    }

    /**
     * StoqS «امتیاز با تخفیف دیگر جمع نشود» (loyalty.redeem_alone): club points are a discount too,
     * so they are not spent on an order that already has another one: a code or a discount rule,
     * and a sale / campaign price as well while «قیمت حراج/کمپین هم تخفیف حساب شود»
     * (redeem_alone_sale, on unless StoqS says 0) is on. A StoqS without the setting sends nothing: off.
     */
    private function pointsBlocked(array $loyalty): bool
    {
        if (empty($loyalty['redeem_alone'])) {
            return false;
        }
        if ($this->cart->appliedCoupon()) {
            return true;
        }
        $sale = 0;
        $other = 0;
        $saleInDiscount = 0; // campaign rules the local engine counts inside combinedDiscount()
        foreach ($this->cart->lineDiscounts() as $parts) {
            foreach ($parts as $d) {
                $amount = (int) ($d['amount'] ?? 0);
                if (in_array($d['type'] ?? '', ['price_sheet', 'campaign'], true)) {
                    $sale += $amount;
                    if (($d['layer'] ?? null) !== 'price') {
                        $saleInDiscount += $amount;
                    }
                } else {
                    $other += $amount;
                }
            }
        }
        if (max($other, $this->cart->combinedDiscount() - $saleInDiscount) > 0) {
            return true;
        }
        if ((int) ($loyalty['redeem_alone_sale'] ?? 1) !== 1) {
            return false;
        }
        foreach ($this->cart->lines() as $l) { // a plain «قیمت قبل» markdown with no campaign behind it
            if ((int) $l['variant']->product->compare_at_price > (int) $l['unit_price']) {
                $sale++;
            }
        }

        return $sale > 0;
    }

    /**
     * Validate a loyalty redemption against the live StoqS balance.
     *
     * @return array{0:int,1:int,2:bool} [points spent, toman discount, refused because another discount is on]
     */
    private function resolveRedemption(Request $request, StockKeepingClient $stockKeeping, int $cap): array
    {
        $requested = max(0, (int) $request->input('redeem_points', 0));
        $user = $request->user();
        if ($requested <= 0 || ! config('stockkeeping.enabled') || ! $user->phone) {
            return [0, 0];
        }
        $crm = $stockKeeping->findCustomer($user->phone);
        if (! $crm || ! ($crm['ok'] ?? false) || empty($crm['loyalty'])) {
            return [0, 0];
        }
        $balance = (int) ($crm['customer']['loyalty_points'] ?? 0);
        $tpp = (float) ($crm['loyalty']['toman_per_point'] ?? 0);
        $min = (int) ($crm['loyalty']['min_redeem_points'] ?? 0);
        if ($this->pointsBlocked($crm['loyalty'])) {
            return [0, 0, true];
        }
        $points = min($requested, $balance);
        if ($points < $min || $tpp <= 0) {
            return [0, 0];
        }
        $discount = min((int) floor($points * $tpp), $cap);
        $points = (int) floor($discount / $tpp); // points actually spent for the capped value

        return [$points, $discount];
    }

    public function place(Request $request, StockKeepingClient $stockKeeping): RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'string'],
            'shipping_method_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'exists:payment_methods,key'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'recipient_name' => ['required_if:address_id,new', 'string', 'max:100'],
            'phone' => ['required_if:address_id,new', 'string', 'max:20'],
            'province' => ['required_if:address_id,new', 'string', Rule::in(config('provinces'))],
            'city' => ['required_if:address_id,new', 'string', 'max:60'],
            'line' => ['required_if:address_id,new', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'gift_wrap' => ['nullable', 'boolean'],
            'gift_message' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'سبد خرید شما خالی است.');
        }

        // Check discount rule blocks.
        if ($this->cart->hasRuleBlocks()) {
            $messages = $this->cart->ruleBlockMessages();
            return redirect()->route('cart.index')->with('error', implode(' ', $messages));
        }

        if ($data['address_id'] === 'new') {
            $address = Address::create([
                'user_id' => $request->user()->id,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'province' => $data['province'],
                'city' => $data['city'],
                'line' => $data['line'],
                'postal_code' => $data['postal_code'] ?? null,
                'is_default' => !$request->user()->addresses()->exists(),
            ]);
        } else {
            $address = Address::where('user_id', $request->user()->id)->findOrFail((int) $data['address_id']);
        }

        // Login is phone-only, so the account often has no name until the first
        // checkout. Back-fill it from the delivery recipient when it's still empty.
        $user = $request->user();
        if (blank($user->name) && filled($address->recipient_name)) {
            $user->update(['name' => $address->recipient_name]);
        }

        $shipping = ShippingMethod::where('is_active', true)->findOrFail($data['shipping_method_id']);
        $method = PaymentMethod::active()->where('key', $data['payment_method'])->firstOrFail();
        if (! $this->gateways->usable($method)) {
            return back()->with('error', 'این روش پرداخت فعلاً در دسترس نیست؛ روش دیگری را انتخاب کنید.');
        }

        // lines() clamps each line to current stock and drops sold-out variants.
        // If that emptied the cart, stock changed since they added items.
        $lines = $this->cart->lines();
        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'موجودی کالاهای سبد تغییر کرده است؛ لطفاً سبد خرید را بررسی کنید.');
        }
        $subtotal = (int) $lines->sum('line_total');

        // Pass checkout selections to the discount rule engine for conditions
        // that depend on payment method, shipping method, or city.
        $this->cart->setRuleContext([
            'payment_method_key' => $data['payment_method'],
            'shipping_method_id' => (int) $data['shipping_method_id'],
            'shipping_city' => $address->city ?? '',
        ]);

        // StoqS-managed promotions: StoqS's own answer for this order (POST /promotions/evaluate);
        // unreachable = sold on the local evaluation, marked «local». Null when the shop runs its
        // own promotions: nothing below changes then.
        $promo = $this->cart->promotionsCheck();
        if ($promo && $promo['refresh']) {
            // StoqS's answer costs more than the cart showed (or a price changed): the customer
            // sees the new amount on the cart before paying.
            return redirect()->route('cart.index')->with('error', $promo['message'] ?? 'مبلغ سبد به‌روز شد؛ لطفاً سبد خرید را دوباره ببینید.');
        }
        if ($promo && $this->cart->hasRuleBlocks()) {   // StoqS's answer may block what the local check let through
            return redirect()->route('cart.index')->with('error', implode(' ', $this->cart->ruleBlockMessages()));
        }

        // Combined discount: coupon + promotion + discount rules.
        $coupon = $this->cart->appliedCoupon();
        $discount = $this->cart->combinedDiscount();
        // Surcharges from discount rules (e.g. item surcharge).
        $surcharge = $this->cart->surcharge();
        // Free shipping from coupon OR discount rules.
        $shippingCost = $this->cart->hasFreeShipping() ? 0 : $shipping->costFor($subtotal, $address->province);

        // Loyalty redemption (re-validated against the live StoqS balance).
        [$redeemPoints, $loyaltyDiscount, $pointsRefused] = $this->resolveRedemption(
            $request, $stockKeeping, max(0, $subtotal - $discount)
        ) + [2 => false];
        if ($pointsRefused) {
            // The page offered the points, but a discount the page did not know of yet (payment,
            // city, a code from another tab) is on: say so instead of quietly charging more.
            return back()->withInput()->with('error', 'امتیاز باشگاه با تخفیف دیگر جمع نمی‌شود. برای استفاده از امتیاز، کد یا تخفیف را بردارید؛ یا بدون امتیاز ادامه دهید.');
        }

        // Gift wrap (admin-configured per-item × items, free over a threshold).
        // Recomputed server-side from current settings — never trust the client.
        $giftWrap = $request->boolean('gift_wrap');
        $giftMessage = $giftWrap ? ($data['gift_message'] ?? null) : null;
        $giftWrapPrice = 0;
        if ($giftWrap && \App\Models\Setting::get('site.giftwrap_enabled')) {
            $perItem = (int) \App\Models\Setting::get('site.giftwrap_per_item_price', 0);
            $freeOver = (int) \App\Models\Setting::get('site.giftwrap_free_over', 0);
            if ($freeOver > 0 && $subtotal >= $freeOver) {
                $giftWrapPrice = 0;
            } else {
                $giftWrapPrice = max(0, $perItem * (int) $lines->sum('quantity'));
            }
        } else {
            // Admin disabled the option after the customer loaded the page — discard.
            $giftWrap = false;
            $giftMessage = null;
        }

        $total = max(0, $subtotal + $shippingCost + $surcharge + $giftWrapPrice - $discount - $loyaltyDiscount);

        // Gift card draws down the payable amount (a payment, not a discount).
        $giftCard = $this->cart->giftCard();
        $giftUsed = $giftCard ? (int) min($giftCard->balance, $total) : 0;
        $payable = max(0, $total - $giftUsed);

        // What each line got (outlet / coupon / rules), for the StoqS sale report.
        $lineDiscounts = $this->cart->lineDiscounts();

        // Track which discount rules were applied for used_count increment.
        $appliedRuleIds = collect($this->cart->ruleDiscountBreakdown())
            ->pluck('rule_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        // StoqS-managed: the rules are StoqS's (their ids are not this shop's), the order keeps
        // how it was priced and each line's list price; StoqS records the code's use.
        $promoCols = [];
        $listPrices = [];
        if ($promo) {
            $appliedRuleIds = [];
            $promoCols = ['promo_eval' => $promo['eval'], 'promo_version' => $promo['version']];
            $listPrices = $this->cart->listPrices();
        }

        $order = DB::transaction(function () use ($request, $data, $address, $shipping, $lines, $subtotal, $shippingCost, $surcharge, $discount, $coupon, $redeemPoints, $loyaltyDiscount, $giftCard, $giftUsed, $total, $appliedRuleIds, $giftWrap, $giftWrapPrice, $giftMessage, $lineDiscounts, $promoCols, $listPrices) {
            $order = Order::create($promoCols + [
                'number' => Order::generateNumber(),
                'user_id' => $request->user()->id,
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'coupon_code' => $coupon?->code,
                'applied_discount_rules' => $appliedRuleIds,
                'loyalty_points_used' => $redeemPoints,
                'loyalty_discount' => $loyaltyDiscount,
                'gift_card_code' => $giftCard?->code,
                'gift_used' => $giftUsed,
                'total' => $total,
                'shipping_method_id' => $shipping->id,
                'shipping_method_name' => $shipping->name,
                'shipping_cost_on_delivery' => (bool) $shipping->cost_on_delivery,
                'shipping_address' => $address->toSnapshot(),
                'customer_name' => $address->recipient_name,
                'customer_phone' => $address->phone,
                'placed_at' => now(),
                'customer_note' => $data['customer_note'] ?? null,
                'gift_wrap' => $giftWrap,
                'gift_wrap_price' => $giftWrapPrice,
                'gift_message' => $giftMessage,
            ]);

            foreach ($lines as $line) {
                $variant = $line['variant'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->product->name,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'sku' => $variant->sku,
                    'stockkeeping_variant_id' => $variant->stockkeeping_variant_id,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'discounts' => $lineDiscounts[$variant->id] ?? null,
                ] + (isset($listPrices[$variant->id]) ? ['list_price' => $listPrices[$variant->id]] : []));
            }

            return $order;
        });

        // Order came from an Instagram purchase link → let the admin's «لینک‌های من» show it.
        if ($sellLinkId = $request->session()->pull('sell_link_id')) {
            \App\Models\SellLink::whereKey($sellLinkId)->update(['order_id' => $order->id]);
        }

        // Nothing left to pay: record what covered it (a gift card, club points, or nothing),
        // so admin, Telegram and reconciliation never call club points a gift card.
        $coveredBy = $giftUsed > 0 ? 'gift' : ($loyaltyDiscount > 0 ? 'loyalty' : 'free');
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => $payable <= 0 ? $coveredBy : $method->key,
            'amount' => $payable,
            'status' => Payment::STATUS_PENDING,
        ]);

        // Gift card / club points cover the whole order → no gateway, finalize immediately.
        if ($payable <= 0) {
            $refPrefix = ['gift' => 'GIFT', 'loyalty' => 'CLUB', 'free' => 'FREE'][$coveredBy];
            $this->finalizePaidOrder($order, $payment, ['success' => true, 'ref_id' => $refPrefix.'-'.$order->number]);
            \App\Jobs\ProcessPaidOrder::dispatchSync($order->id); // inline — see callback() note
            $this->cart->clear();

            return redirect()->route('checkout.success', $order->number);
        }

        $gateway = $this->gateways->make($method);
        $callbackUrl = route('checkout.callback', ['method' => $method->key]);

        try {
            $result = $gateway->request(
                amountToman: $payable,
                callbackUrl: $callbackUrl,
                description: 'پرداخت سفارش '.$order->number,
                metadata: ['mobile' => $order->customer_phone, 'order_id' => $order->number],
            );
        } catch (Throwable $e) {
            report($e); // surface in logs for diagnosis
            $payment->update(['status' => Payment::STATUS_FAILED, 'meta' => ['error' => $e->getMessage()]]);
            $order->update(['status' => Order::STATUS_FAILED]);

            // Show the real gateway reason (amount below minimum, bad merchant id,
            // gateway unreachable…) so the cause is diagnosable, not a blanket message.
            return redirect()->route('cart.index')
                ->with('error', 'اتصال به درگاه پرداخت ناموفق بود: '.$e->getMessage());
        }

        $payment->update(['authority' => $result['identifier']]);

        // Validate the redirect URL to prevent open redirect attacks.
        $redirectUrl = $result['redirect_url'];
        if (!filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            // Fallback to cart if URL is invalid.
            return redirect()->route('cart.index')->with('error', 'اتصال به درگاه پرداخت ناموفق بود. دوباره تلاش کنید.');
        }

        return redirect()->away($redirectUrl);
    }

    public function callback(Request $request, PaymentMethod $method, StockKeepingReporter $reporter): RedirectResponse
    {
        if (! $this->gateways->usable($method)) {
            \Illuminate\Support\Facades\Log::warning('[payment] callback for an unusable method', ['method' => $method->key, 'params' => $request->all()]);

            return redirect()->route('home')->with('error', 'تراکنش یافت نشد.');
        }
        $gateway = $this->gateways->makeReal($method);

        $identifier = $gateway->callbackIdentifier($request);
        if (! $identifier || ! Payment::where('authority', $identifier)->exists()) {
            return redirect()->route('home')->with('error', 'تراکنش یافت نشد.');
        }

        // One callback at a time per payment: a refresh or a second hit waits on the row
        // lock, then sees the order paid, so the order is never finalized twice (coupons,
        // gift card and stock counted twice). Verify runs under the lock for that reason.
        $alert = null;
        [$order, $outcome] = DB::transaction(function () use ($gateway, $request, $identifier, &$alert) {
            $payment = Payment::where('authority', $identifier)->lockForUpdate()->first();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();

            if ($order->isPaid()) {
                return [$order, 'already'];
            }

            if (! $gateway->callbackApproved($request)) {
                $payment->update(['status' => Payment::STATUS_CANCELED]);
                $order->update(['status' => Order::STATUS_FAILED]);

                return [$order, 'failed'];
            }

            try {
                $verify = $gateway->verify($identifier, (int) $payment->amount);
            } catch (Throwable $e) {
                // A thrown verify says nothing about the money: it may have settled.
                report($e);
                $verify = ['success' => false, 'unknown' => true, 'error' => $e->getMessage()];
            }

            if ($verify['unknown'] ?? false) {
                // Keep the order pending so a retry (same callback URL; verify answers
                // "already verified" if the money settled) can still finalize it.
                $payment->update(['meta' => array_merge((array) $payment->meta, [
                    'verify_unknown' => $verify['error'] ?? true, 'retry_url' => $request->fullUrl(),
                ])]);
                $alert = "⚠️ <b>پرداخت تأیید نشد</b>\nسفارش {$order->number} — کد پیگیری {$identifier}\nپاسخ درگاه نامشخص است؛ وضعیت را در پنل درگاه بررسی کنید.";

                return [$order, 'unknown'];
            }

            if (! ($verify['success'] ?? false)) {
                $payment->update(['status' => Payment::STATUS_FAILED, 'meta' => $verify]);
                $order->update(['status' => Order::STATUS_FAILED]);
                if ($verify['amount_mismatch'] ?? false) {
                    $alert = "⚠️ <b>مبلغ پرداخت با سفارش نمی‌خواند</b>\nسفارش {$order->number} — کد پیگیری {$identifier}\nمبلغ واریزی را بازگردانید.";
                }

                return [$order, 'failed'];
            }

            $this->finalizePaidOrder($order, $payment, $verify);

            return [$order, 'paid'];
        });

        if ($alert) {
            try {
                app(\App\Services\Telegram\TelegramNotifier::class)->notifyAdmins($alert);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($outcome === 'paid') {
            // Sale report (StoqS) + customer SMS + admin Telegram. Run INLINE via
            // dispatchSync so the customer SMS + receipt side effects fire the moment
            // the gateway callback lands — even on shared hosting with no queue worker
            // (QUEUE_CONNECTION=database would otherwise leave the job unran). Each
            // effect is independently try/caught inside the job, so a slow/failed SMS
            // or StoqS call can't break the callback or the others. After commit, and
            // only for the request that finalized the order.
            \App\Jobs\ProcessPaidOrder::dispatchSync($order->id);
            $this->cart->clear();
        }

        return in_array($outcome, ['paid', 'already'], true)
            ? redirect()->route('checkout.success', $order->number)
            : redirect()->route('checkout.failed', $order->number);
    }

    private function finalizePaidOrder(Order $order, Payment $payment, array $verify): void
    {
        DB::transaction(function () use ($order, $payment, $verify) {
            $payment->update([
                'status' => Payment::STATUS_PAID,
                'ref_id' => $verify['ref_id'] ?? null,
                'card_pan' => $verify['card_pan'] ?? null,
                'paid_at' => now(),
            ]);

            $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            // Count the coupon redemption now that payment succeeded. A StoqS-priced order
            // (promo_eval set) used StoqS's code and rules: StoqS counts them from the sale, and
            // the local copy is counted too when the code is this shop's own coupon (registered
            // by this key, external_id = its id).
            if ($order->coupon_code && $order->promo_eval === null) {
                \App\Models\Coupon::where('code', $order->coupon_code)->increment('used_count');
            } elseif ($order->coupon_code && ($own = \App\Services\Discount\StoqsCart::ownCouponId((string) $order->coupon_code)) !== null) {
                \App\Models\Coupon::whereKey($own)->increment('used_count');
            }

            // Count discount rule usage.
            if (! empty($order->applied_discount_rules) && $order->promo_eval === null) {
                \App\Models\DiscountRule::whereIn('id', $order->applied_discount_rules)->increment('used_count');
            }

            // Draw down the gift card balance.
            if ($order->gift_card_code && $order->gift_used > 0) {
                \App\Models\GiftCard::where('code', $order->gift_card_code)->decrement('balance', $order->gift_used);
            }

            // Decrement local cached stock (authoritative qty lives in stock-keeping).
            // A bundle's order item points at a placeholder variant with no stock of
            // its own — decrement the real child variants instead, one per
            // (bundle_item × order quantity).
            $order->loadMissing(['items.variant.product.bundleItems.variant']);
            foreach ($order->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }
                $product = $variant->product;
                if ($product?->is_bundle) {
                    foreach ($product->bundleItems as $bi) {
                        $child = $bi->variant;
                        if (! $child) {
                            continue;
                        }
                        $need = (int) $item->quantity * (int) $bi->quantity;
                        $child->decrement('stock_qty', min($need, max(0, (int) $child->stock_qty)));
                    }

                    continue;
                }
                $variant->decrement('stock_qty', min($item->quantity, max(0, (int) $variant->stock_qty)));
            }
        });
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        // Telegram bot for order tracking + support: connect if not yet linked, else open it.
        $botUsername = (string) config('telegram.bot_username');
        $telegramReady = (bool) config('telegram.bot_token') && $botUsername !== '';
        $telegramLinked = $telegramReady && \Illuminate\Support\Facades\Schema::hasTable('telegram_chats')
            && \App\Models\TelegramChat::customers()->where('user_id', $order->user_id)->exists();

        return view('checkout.success', [
            'order' => $order->load('items', 'payment'),
            'telegramReady' => $telegramReady,
            'telegramUrl' => $telegramLinked ? 'https://t.me/'.ltrim($botUsername, '@') : route('account.telegram.connect'),
        ]);
    }

    public function failed(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        // A verify with no clear answer leaves the order pending: offer to check again.
        $payment = $order->payment;
        $retryUrl = $order->status === Order::STATUS_PENDING && ($payment?->meta['verify_unknown'] ?? false)
            ? ($payment->meta['retry_url'] ?? null) : null;

        return view('checkout.failed', ['order' => $order, 'retryUrl' => $retryUrl]);
    }

    public function cities(string $province): \Illuminate\Http\JsonResponse
    {
        $cities = config("cities.$province", []);

        return response()->json($cities);
    }
}
