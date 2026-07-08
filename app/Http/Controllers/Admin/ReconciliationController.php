<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Income / settlement reconciliation.
 *
 * Online gateway payments settle to the merchant's bank in batches (T+1 or
 * weekly), so paid orders and money actually received drift apart. This report
 * lines up every real-money gateway payment against (a) whether it has been
 * marked settled and (b) whether the sale was reported to StoqS, surfacing the
 * two mismatches that matter: unsettled income and orders missing from the POS.
 */
class ReconciliationController extends Controller
{
    /** Statuses that represent a captured gateway transaction. */
    private const RECONCILABLE = [Payment::STATUS_PAID, Payment::STATUS_REFUNDED];

    public function index(Request $request): View
    {
        $fromG = Jalali::parse($request->string('from')->toString() ?: null);
        $toG = Jalali::parse($request->string('to')->toString() ?: null);
        $gateway = $request->string('gateway')->toString();
        $settlement = $request->string('settlement')->toString(); // '', 'settled', 'unsettled'

        $payments = $this->baseQuery($fromG, $toG, $gateway, $settlement)
            ->with('order')
            ->latest('paid_at')
            ->paginate(40)
            ->withQueryString();

        // Totals over the full filtered set (not just the current page).
        $all = $this->baseQuery($fromG, $toG, $gateway, $settlement)->with('order')->get();
        $gross = $all->where('status', Payment::STATUS_PAID)->sum('amount');
        $refunded = $all->where('status', Payment::STATUS_REFUNDED)->sum('amount');
        $settled = $all->filter(fn ($p) => $p->isSettled())->where('status', Payment::STATUS_PAID)->sum('amount');
        $notReported = $all->where('status', Payment::STATUS_PAID)
            ->filter(fn ($p) => empty($p->order?->stockkeeping_sale_id))->count();

        $byGateway = $all->groupBy('gateway')->map(fn ($g) => [
            'count' => $g->where('status', Payment::STATUS_PAID)->count(),
            'gross' => $g->where('status', Payment::STATUS_PAID)->sum('amount'),
            'settled' => $g->filter(fn ($p) => $p->isSettled())->where('status', Payment::STATUS_PAID)->sum('amount'),
        ])->sortKeys();

        return view('admin.reports.reconciliation', [
            'payments' => $payments,
            'gateways' => $this->knownGateways(),
            'byGateway' => $byGateway,
            'summary' => [
                'gross' => (int) $gross,
                'refunded' => (int) $refunded,
                'net' => (int) ($gross - $refunded),
                'settled' => (int) $settled,
                'unsettled' => (int) ($gross - $settled),
                'notReported' => $notReported,
            ],
            'filters' => compact('gateway', 'settlement') + [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
        ]);
    }

    /** Mark a single payment as settled (money received in the bank). */
    public function settle(Request $request, Payment $payment): RedirectResponse
    {
        $ref = $request->string('settlement_ref')->toString();
        $payment->update([
            'meta' => array_merge((array) $payment->meta, [
                'settled_at' => now()->toDateTimeString(),
                'settlement_ref' => $ref ?: null,
            ]),
        ]);

        return back()->with('success', 'پرداخت به‌عنوان تسویه‌شده ثبت شد.');
    }

    /** Mark every payment in the current filter as settled. */
    public function settleAll(Request $request): RedirectResponse
    {
        $fromG = Jalali::parse($request->string('from')->toString() ?: null);
        $toG = Jalali::parse($request->string('to')->toString() ?: null);

        $count = 0;
        $this->baseQuery($fromG, $toG, $request->string('gateway')->toString(), 'unsettled')
            ->where('status', Payment::STATUS_PAID)
            ->each(function (Payment $p) use (&$count) {
                $p->update(['meta' => array_merge((array) $p->meta, [
                    'settled_at' => now()->toDateTimeString(),
                ])]);
                $count++;
            });

        return back()->with('success', \App\Support\Money::toPersianDigits((string) $count).' پرداخت تسویه شد.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Payment>
     */
    private function baseQuery(?string $fromG, ?string $toG, string $gateway, string $settlement)
    {
        return Payment::query()
            ->whereIn('status', self::RECONCILABLE)
            ->where('amount', '>', 0)
            ->where('gateway', '!=', 'gift')
            ->whereNotNull('gateway')
            ->when($fromG, fn ($q) => $q->whereDate('paid_at', '>=', $fromG))
            ->when($toG, fn ($q) => $q->whereDate('paid_at', '<=', $toG))
            ->when($gateway, fn ($q) => $q->where('gateway', $gateway))
            ->when($settlement === 'settled', fn ($q) => $q->whereNotNull('meta->settled_at'))
            ->when($settlement === 'unsettled', fn ($q) => $q->whereNull('meta->settled_at'));
    }

    /** @return array<string,string> */
    private function knownGateways(): array
    {
        return \App\Models\PaymentMethod::query()
            ->orderBy('position')
            ->pluck('label', 'key')
            ->all();
    }
}
