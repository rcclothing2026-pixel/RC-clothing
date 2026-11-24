<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Orders paid in full by club points (or by nothing) were saved with gateway «gift» and
 * shown as «کارت هدیه». Relabel the ones that used no gift card.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.gateway', 'gift')
            ->where(fn ($q) => $q->whereNull('orders.gift_used')->orWhere('orders.gift_used', '<=', 0))
            ->get(['payments.id', 'orders.loyalty_discount']);
        foreach ($rows as $r) {
            DB::table('payments')->where('id', $r->id)
                ->update(['gateway' => (int) $r->loyalty_discount > 0 ? 'loyalty' : 'free']);
        }
    }

    public function down(): void
    {
        DB::table('payments')->whereIn('gateway', ['loyalty', 'free'])->update(['gateway' => 'gift']);
    }
};
