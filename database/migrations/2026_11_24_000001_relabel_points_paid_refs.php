<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Follows 2026_11_24_000000: the payments it relabelled to «loyalty» / «free» still carry the
 * old GIFT-<number> tracking code. Give them CLUB-<number> / FREE-<number>, as new orders get.
 */
return new class extends Migration
{
    private const PREFIXES = ['loyalty' => 'CLUB-', 'free' => 'FREE-'];

    public function up(): void
    {
        foreach (self::PREFIXES as $gateway => $prefix) {
            $this->swap($gateway, 'GIFT-', $prefix);
        }
    }

    public function down(): void
    {
        foreach (self::PREFIXES as $gateway => $prefix) {
            $this->swap($gateway, $prefix, 'GIFT-');
        }
    }

    private function swap(string $gateway, string $from, string $to): void
    {
        $rows = DB::table('payments')->where('gateway', $gateway)
            ->where('ref_id', 'like', $from.'%')->get(['id', 'ref_id']);
        foreach ($rows as $r) {
            DB::table('payments')->where('id', $r->id)
                ->update(['ref_id' => $to.substr($r->ref_id, strlen($from))]);
        }
    }
};
