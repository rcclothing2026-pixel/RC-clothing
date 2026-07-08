<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $paid = [
            Order::STATUS_PAID, Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED, Order::STATUS_DELIVERED,
        ];

        // 14-day revenue series (filled with zeros), Jalali day labels.
        $since = Carbon::today()->subDays(13);
        $byDay = Order::whereIn('status', $paid)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, SUM(total) as rev')
            ->groupBy('d')->pluck('rev', 'd');

        $series = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $since->copy()->addDays($i);
            $series[] = [
                'label' => Jalali::format($day),
                'rev' => (int) ($byDay[$day->toDateString()] ?? 0),
            ];
        }

        $topProducts = OrderItem::whereHas('order', fn ($q) => $q->whereIn('status', $paid))
            ->selectRaw('name, SUM(quantity) as qty, SUM(line_total) as revenue')
            ->groupBy('name')->orderByDesc('qty')->take(5)->get();

        // 7-day vs prior 7-day comparison (week-over-week revenue Δ%).
        $thisWeekRev = (int) Order::whereIn('status', $paid)
            ->where('created_at', '>=', now()->subDays(7))->sum('total');
        $priorWeekRev = (int) Order::whereIn('status', $paid)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->sum('total');
        $weekDeltaPct = $priorWeekRev > 0
            ? (int) round(($thisWeekRev - $priorWeekRev) / $priorWeekRev * 100)
            : null;

        return view('admin.dashboard', [
            'productCount' => Product::count(),
            'orderCount' => Order::count(),
            'pendingCount' => Order::where('status', Order::STATUS_PENDING)->count(),
            'revenue' => (int) Order::whereIn('status', $paid)->sum('total'),
            'todayRevenue' => (int) Order::whereIn('status', $paid)->whereDate('created_at', today())->sum('total'),
            'todayOrderCount' => Order::whereIn('status', $paid)->whereDate('created_at', today())->count(),
            'monthRevenue' => (int) Order::whereIn('status', $paid)->where('created_at', '>=', now()->startOfMonth())->sum('total'),
            'weekRevenue' => $thisWeekRev,
            'weekDeltaPct' => $weekDeltaPct,
            'unreadSupport' => ContactMessage::where('direction', 'in')->where('is_read', false)->count(),
            'failedPayments' => Order::where('status', Order::STATUS_FAILED)
                ->where('created_at', '>=', now()->subDays(7))->count(),
            'salesSeries' => $series,
            'topProducts' => $topProducts,
            'lowStock' => ProductVariant::with('product')
                ->where('is_active', true)
                ->where('stock_qty', '<=', 2)
                ->take(10)->get(),
            'recentOrders' => Order::latest()->take(8)->get(),
        ]);
    }
}
