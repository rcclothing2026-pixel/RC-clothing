<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockkeeepingLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StockLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockkeeepingLog::with('order')
            ->when($request->input('type'), fn ($q, $t) => $q->where('event_type', $t))
            ->when($request->input('direction'), fn ($q, $d) => $q->where('direction', $d))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('ref'), fn ($q, $r) => $q->where('ref', 'like', "%{$r}%"))
            ->when($request->input('from'), fn ($q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($request->input('to'), fn ($q, $t) => $q->whereDate('created_at', '<=', $t))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.stock-log.index', [
            'logs' => $query,
            'typeLabels' => StockkeeepingLog::TYPE_LABELS,
        ]);
    }
}
