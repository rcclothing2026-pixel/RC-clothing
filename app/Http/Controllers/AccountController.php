<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\TelegramChat;
use App\Services\StockKeeping\StockKeepingClient;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AccountController extends Controller
{
    public function index(Request $request, StockKeepingClient $stockKeeping): View
    {
        $user = $request->user();

        // Unified CRM profile from StoqS (loyalty + in-store & online history),
        // cached briefly so the page doesn't hit the API on every load.
        $crm = null;
        if ($user->phone && config('stockkeeping.enabled')) {
            $crm = Cache::remember('crm:'.$user->phone, now()->addMinutes(10),
                fn () => $stockKeeping->findCustomer($user->phone));
        }

        return view('account.index', [
            'user' => $user,
            'orders' => $user->orders()->take(5)->get(),
            'addresses' => $user->addresses()->get(),
            'crm' => $crm,
            'telegramChat' => \Illuminate\Support\Facades\Schema::hasTable('telegram_chats')
                ? TelegramChat::customers()->where('user_id', $user->id)->first()
                : null,
            'telegramReady' => (bool) config('telegram.bot_token') && (bool) config('telegram.bot_username'),
        ]);
    }

    /** Start the Telegram connect flow — redirect to the bot's deep link. */
    public function connectTelegram(Request $request, TelegramNotifier $tg): RedirectResponse
    {
        $url = $tg->connectUrl($request->user());
        if ($url === '') {
            return back()->with('success', 'اتصال تلگرام در حال حاضر در دسترس نیست.');
        }

        return redirect()->away($url);
    }

    /** Disconnect the customer's linked Telegram chat. */
    public function disconnectTelegram(Request $request, TelegramNotifier $tg): RedirectResponse
    {
        $tg->unlinkCustomer($request->user());

        return back()->with('success', 'اتصال تلگرام قطع شد.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'پروفایل به‌روزرسانی شد.');
    }

    public function orders(Request $request): View
    {
        return view('account.orders', [
            'orders' => $request->user()->orders()->with('items')->paginate(10),
        ]);
    }

    public function showOrder(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('account.order', [
            'order' => $order->load('items', 'payment', 'shippingMethod'),
        ]);
    }

    public function invoice(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('orders.invoice', ['order' => $order->load('items', 'payment')]);
    }

    public function returnForm(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->isPaid(), 403);

        return view('account.return', ['order' => $order->load('items')]);
    }

    public function storeReturn(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->isPaid(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $selected = [];
        foreach ($order->items as $item) {
            $qty = (int) ($data['items'][$item->id] ?? 0);
            if ($qty > 0) {
                $selected[] = [
                    'order_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'stockkeeping_variant_id' => $item->stockkeeping_variant_id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'size' => $item->size,
                    'quantity' => min($qty, $item->quantity),
                ];
            }
        }
        if (! $selected) {
            return back()->with('error', 'حداقل یک کالا را برای مرجوعی انتخاب کنید.');
        }

        ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'status' => ReturnRequest::STATUS_REQUESTED,
            'reason' => $data['reason'],
            'items' => $selected,
        ]);

        return redirect()->route('account.orders.show', $order)->with('success', 'درخواست مرجوعی ثبت شد و در حال بررسی است.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $this->validateAddress($request);
        $data['user_id'] = $request->user()->id;
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = Address::create($data);

        // If the account has no name yet, adopt the recipient name and push the
        // customer to the StoqS CRM (the observer fires on the name change).
        $user = $request->user();
        if (blank($user->name) && filled($address->recipient_name)) {
            $user->update(['name' => $address->recipient_name]);
        } else {
            app(\App\Services\StockKeeping\StockKeepingReporter::class)->syncCustomer($user);
        }

        return back()->with('success', 'آدرس جدید ذخیره شد.');
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $address->delete();

        return back()->with('success', 'آدرس حذف شد.');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', \Illuminate\Validation\Rule::in(config('provinces'))],
            'city' => ['required', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'line' => ['required', 'string', 'max:500'],
        ]);
    }
}
