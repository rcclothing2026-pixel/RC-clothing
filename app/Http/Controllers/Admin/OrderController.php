<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountRule;
use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\StockKeepingReporter;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with('items')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where(fn ($w) => $w
                ->where('number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_phone', 'like', "%{$term}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUS_LABELS,
        ]);
    }

    /** Bulk status update for selected orders. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Order::STATUS_LABELS))],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        Order::whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return back()->with('success', \App\Support\Money::toPersianDigits((string) count($data['ids'])).' سفارش به‌روزرسانی شد.');
    }

    public function show(Order $order): View
    {
        // The latest StoqS sale-report event for this order, so the panel can
        // show WHY it hasn't reported yet (queued vs failed + the error text)
        // instead of a blind "در صف ارسال".
        $stoqsEvent = IntegrationEvent::where('type', IntegrationEvent::TYPE_SALE_CREATED)
            ->where('payload->order_number', $order->number)
            ->latest('id')->first();

        return view('admin.orders.show', [
            'order' => $order->load('items', 'payment', 'user', 'shippingMethod'),
            'statuses' => Order::STATUS_LABELS,
            'stoqsEvent' => $stoqsEvent,
        ]);
    }

    /** Re-attempt the StoqS sale report for one order, surfacing the real error. */
    public function resendStoqs(Order $order, StockKeepingReporter $reporter): RedirectResponse
    {
        if ($order->stockkeeping_sale_id) {
            return back()->with('success', 'این سفارش قبلاً در StoqS ثبت شده است (کد '.$order->stockkeeping_sale_id.').');
        }

        // Reuse the existing queued/failed sale event so we never create a
        // duplicate sale in StoqS; only build a fresh one if none exists.
        $event = IntegrationEvent::where('type', IntegrationEvent::TYPE_SALE_CREATED)
            ->where('status', '!=', 'sent')
            ->where('payload->order_number', $order->number)
            ->latest('id')->first();

        if ($event) {
            // Rebuild the payload from the order's CURRENT state so a just-added
            // barcode/StoqS mapping is picked up (the stored payload was frozen
            // at purchase time). Only the sale event is touched — income/customer
            // aren't re-enqueued, so nothing double-counts.
            $event->update([
                'payload' => $reporter->buildSalePayload($order),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ]);
            $reporter->flushPending();
        } else {
            $reporter->reportPaidOrder($order->fresh(['items', 'payment', 'user'])); // enqueues + flushes
        }

        $order->refresh();
        if ($order->stockkeeping_sale_id) {
            return back()->with('success', 'سفارش با موفقیت به StoqS ارسال شد. کد فروش: '.$order->stockkeeping_sale_id);
        }

        $err = IntegrationEvent::where('type', IntegrationEvent::TYPE_SALE_CREATED)
            ->where('payload->order_number', $order->number)
            ->latest('id')->value('last_error');

        return back()->with('error', 'ارسال به StoqS ناموفق بود — '.($err ?: 'خطای نامشخص؛ گزارش خطاها را ببینید.'));
    }

    public function invoice(Order $order): View
    {
        return view('orders.invoice', ['order' => $order->load('items', 'payment')]);
    }

    public function updateStatus(Request $request, Order $order, TelegramNotifier $telegram): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Order::STATUS_LABELS))],
        ]);

        $oldStatus = $order->status;
        $wasPaid = $order->isPaid();
        $order->update(['status' => $data['status']]);

        if ($data['status'] === Order::STATUS_SHIPPED) {
            try {
                app(\App\Services\Sms\SmsService::class)->notifyOrderShipped($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Cancelling a previously-paid (and reported) order returns the stock,
        // locally and back to StoqS, reversing the recorded sale.
        if ($data['status'] === Order::STATUS_CANCELED && $wasPaid) {
            $this->restockCancelled($order);
        }

        if ($telegram->enabled() && $data['status'] !== $oldStatus) {
            try {
                $telegram->notifyOrderStatusChanged($order, $oldStatus);
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                $telegram->notifyCustomerStatus($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'وضعیت سفارش به‌روزرسانی شد.');
    }

    public function refund(Order $order, \App\Services\Payment\PaymentGatewayFactory $gateways, TelegramNotifier $telegram): RedirectResponse
    {
        if (! $order->isPaid()) {
            return back()->with('error', 'فقط سفارش پرداخت‌شده قابل بازپرداخت است.');
        }
        $payment = $order->payment;
        $gatewayMsg = 'بازپرداخت را در پنل درگاه به‌صورت دستی انجام دهید.';

        if ($payment && $payment->gateway && $payment->gateway !== 'gift' && $payment->authority) {
            $method = \App\Models\PaymentMethod::where('key', $payment->gateway)->first();
            if ($method) {
                $gw = $gateways->make($method);
                if ($gw instanceof \App\Services\Payment\SupportsRefund) {
                    try {
                        $ok = $gw->refund((string) $payment->authority, (int) $payment->amount);
                        $gatewayMsg = $ok ? 'بازپرداخت در درگاه ثبت شد.' : 'درگاه بازپرداخت خودکار را نپذیرفت؛ دستی پیگیری کنید.';
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        $payment?->update([
            'status' => \App\Models\Payment::STATUS_REFUNDED,
            'meta' => array_merge((array) $payment->meta, ['refunded_at' => now()->toDateTimeString()]),
        ]);
        $order->update(['status' => Order::STATUS_CANCELED]);
        $this->restockCancelled($order); // return stock locally + StoqS

        if ($telegram->enabled()) {
            try {
                $telegram->notifyOrderCancelled($order);
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                $telegram->notifyCustomerStatus($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'سفارش بازپرداخت و لغو شد. '.$gatewayMsg);
    }

    private function restockCancelled(Order $order): void
    {
        $order->loadMissing('items.variant');
        $skItems = [];
        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }
            if ($item->variant) {
                $item->variant->increment('stock_qty', $qty);
            }
            if ($item->stockkeeping_variant_id) {
                $skItems[] = ['variant_id' => (int) $item->stockkeeping_variant_id, 'delta' => $qty];
            } elseif ($item->sku) {
                $skItems[] = ['barcode' => (string) $item->sku, 'delta' => $qty];
            }
        }
        if ($skItems) {
            $ref = 'CXL-'.$order->number;
            try {
                app(\App\Services\StockKeeping\StockKeepingClient::class)
                    ->adjustStock($skItems, 'website_cancel', $ref);
                StockkeeepingLog::record(
                    StockkeeepingLog::TYPE_CANCEL_RESTOCK, 'out', $ref, $order->id, $skItems,
                );
            } catch (\Throwable $e) {
                report($e);
                StockkeeepingLog::record(
                    StockkeeepingLog::TYPE_CANCEL_RESTOCK, 'out', $ref, $order->id, $skItems,
                    'failed', null, $e->getMessage(),
                );
            }
        }
    }
}
