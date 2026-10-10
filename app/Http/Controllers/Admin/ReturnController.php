<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $returns = ReturnRequest::with('order')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.returns.index', [
            'returns' => $returns,
            'statuses' => ReturnRequest::STATUS_LABELS,
        ]);
    }

    public function show(ReturnRequest $return): View
    {
        return view('admin.returns.show', [
            'return' => $return->load('order', 'user'),
            'statuses' => ReturnRequest::STATUS_LABELS,
        ]);
    }

    public function update(Request $request, ReturnRequest $return, StockKeepingClient $stockKeeping): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,received'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $return->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'resolved_at' => now(),
        ]);

        // On physical receipt, return the items to stock (locally + StoqS).
        if ($data['status'] === ReturnRequest::STATUS_RECEIVED) {
            $this->restock($return, $stockKeeping);
        }

        return redirect()->route('admin.returns.show', $return)->with('success', 'وضعیت مرجوعی به‌روزرسانی شد.');
    }

    private function restock(ReturnRequest $return, StockKeepingClient $stockKeeping): void
    {
        $skItems = [];
        foreach ($return->items as $it) {
            $qty = (int) ($it['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if (! empty($it['product_variant_id'])) {
                ProductVariant::where('id', $it['product_variant_id'])->increment('stock_qty', $qty);
            }
            if (! empty($it['stockkeeping_variant_id'])) {
                $skItems[] = ['variant_id' => (int) $it['stockkeeping_variant_id'], 'delta' => $qty];
            } elseif (! empty($it['sku'])) {
                $skItems[] = ['barcode' => (string) $it['sku'], 'delta' => $qty];
            }
        }

        if ($skItems) {
            $ref = 'RET-'.$return->id;
            $orderId = $return->order_id ?? null;
            try {
                $stockKeeping->adjustStock($skItems, 'website_return', $ref);
                StockkeeepingLog::record(
                    StockkeeepingLog::TYPE_RETURN_RESTOCK, 'out', $ref, $orderId, $skItems,
                );
            } catch (Throwable $e) {
                report($e); // local restock already done; StoqS sync is best-effort
                StockkeeepingLog::record(
                    StockkeeepingLog::TYPE_RETURN_RESTOCK, 'out', $ref, $orderId, $skItems,
                    'failed', null, $e->getMessage(),
                );
            }
        }
    }
}
