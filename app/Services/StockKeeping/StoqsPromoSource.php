<?php

namespace App\Services\StockKeeping;

use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\StockkeeepingLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Who manages this site's promotions, and the two-way switch (campaign planner, part 3).
 *
 * StoqS holds the truth: promo_source («stoqs» | «shop») with a monotonic promo_source_seq.
 * This site mirrors the last seq it applied in stoqs_promotions (promo_source_seq, _changed_at,
 * _by, _note) and logs every change in stoqs_promo_source_log. A change reaches it three ways:
 *  - StoqS panel / API: the signed webhook promotions.source_changed, applied at once;
 *  - the minute pull: GET /promotions carries promo_source_seq (managed or not), the path that
 *    converges when a webhook failed;
 *  - the site's own admin switch: PUT /promotions/source, and the site flips only after StoqS
 *    answers ok.
 * Applying a seq flips `managed` through the same path a pull-driven flip takes
 * (StoqsPromotions::pull / flipped), so prices and content come back exactly as before. Each
 * applied seq is acknowledged (POST /promotions/ack {source_seq, own_discounts}); until it is,
 * promo_source_ack_pending shows it and the minute job retries.
 */
class StoqsPromoSource
{
    public const STOP_ALL = 'همهٔ تخفیف‌های فعلی سایت متوقف می‌شود';

    private const ORIGINS = ['panel' => 'stoqs_panel', 'api' => 'stoqs_api', 'shop' => 'shop_admin'];

    /** Whether the migration has run (the columns and the log table exist). */
    public static function ready(): bool
    {
        static $ok = null;
        if ($ok === null || app()->runningUnitTests()) {
            try {
                $ok = Schema::hasTable('stoqs_promotions') && Schema::hasColumn('stoqs_promotions', 'promo_source_seq')
                    && Schema::hasTable('stoqs_promo_source_log');
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    /** @return array{source: string, seq: int, changed_at: ?string, by: ?string, note: ?string, pending: bool} */
    public static function state(): array
    {
        $r = StoqsPromotions::row();

        return [
            'source' => ! empty($r->managed) ? 'stoqs' : 'shop',
            'seq' => (int) ($r->promo_source_seq ?? 0),
            'changed_at' => $r->promo_source_changed_at ?? null,
            'by' => $r->promo_source_by ?? null,
            'note' => $r->promo_source_note ?? null,
            'pending' => ! empty($r->promo_source_ack_pending),
        ];
    }

    public static function origin(?string $o): string
    {
        return self::ORIGINS[(string) $o] ?? 'stoqs_api';
    }

    /** The log, newest first. */
    public static function history(int $limit = 100): array
    {
        if (! self::ready()) {
            return [];
        }

        return DB::table('stoqs_promo_source_log')->orderByDesc('id')->limit($limit)->get()->all();
    }

    /**
     * The shop's OWN live discounts, names only (max 50 each): enabled rules inside their dates,
     * and enabled coupons inside their dates and not used up. Sent on every ack; StoqS uses it
     * for the switch guard.
     *
     * @return array{rules: string[], coupons: string[]}
     */
    public static function ownDiscounts(): array
    {
        try {
            $rules = DiscountRule::active()
                ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
                ->orderBy('priority')->orderBy('id')->limit(50)->pluck('name')
                ->map(fn ($n) => mb_substr((string) $n, 0, 120))->values()->all();
            $coupons = Coupon::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
                ->orderBy('id')->limit(50)->pluck('code')
                ->map(fn ($c) => mb_substr((string) $c, 0, 120))->values()->all();
        } catch (\Throwable) {
            return ['rules' => [], 'coupons' => []];
        }

        return ['rules' => $rules, 'coupons' => $coupons];
    }

    /**
     * Apply a switch StoqS made (or one the shop made and StoqS confirmed): only when $seq is
     * greater than the stored one (a stale or replayed seq is a no-op). The seq is stored even
     * when the source is unchanged; when it changed, `managed` flips through the pull path.
     * $ack: tell StoqS (POST /promotions/ack {source_seq}); the shop's own switch skips it.
     * Returns whether it was applied.
     */
    public static function apply(string $source, int $seq, string $origin, ?string $by = null, ?string $note = null,
        ?string $changedAt = null, bool $ack = true): bool
    {
        if (! self::ready() || ! in_array($source, ['stoqs', 'shop'], true) || $seq <= self::state()['seq']) {
            return false;
        }
        $want = $source === 'stoqs';
        $was = (bool) (StoqsPromotions::row()->managed ?? false);
        self::remember($seq, $by, $note, $changedAt, $ack);
        if ($was !== $want) {
            StoqsPromotions::saveState(['etag' => null]);   // a full answer, not a 304
            StoqsPromotions::pull();   // the existing pull-driven flip
            if ((bool) (StoqsPromotions::row()->managed ?? false) !== $want) {   // StoqS unreachable: flip on the word of the switch
                StoqsPromotions::saveState(['managed' => $want, 'promo_source' => $source]);
                StoqsPromotions::flipped($want);
            }
        }
        self::log($seq, $was ? 'stoqs' : 'shop', $source, $origin, $by, $note);
        if ($ack) {
            self::ackSource();
        }

        return true;
    }

    /** The minute pull saw promo_source_seq in the answer (managed or not): apply it when newer. */
    public static function fromPull(array $answer, bool $was, bool $managed): void
    {
        if (! self::ready() || ! isset($answer['promo_source_seq']) || (int) $answer['promo_source_seq'] <= self::state()['seq']) {
            return;
        }
        $seq = (int) $answer['promo_source_seq'];
        self::remember($seq, $answer['promo_source_by'] ?? null, $answer['promo_source_note'] ?? null, $answer['promo_source_changed_at'] ?? null, true);
        self::log($seq, $was ? 'stoqs' : 'shop', $managed ? 'stoqs' : 'shop', 'pull', $answer['promo_source_by'] ?? null, $answer['promo_source_note'] ?? null);
        self::ackSource();
    }

    /** POST /promotions/ack {source_seq, own_discounts}; on failure the pending marker stays and the minute job retries. */
    public static function ackSource(): bool
    {
        if (! self::ready()) {
            return false;
        }
        $seq = self::state()['seq'];
        try {
            StoqsApi::call('POST', 'promotions/ack', [
                'source_seq' => $seq, 'site_url' => StoqsPromotions::siteUrl(), 'own_discounts' => self::ownDiscounts(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[stoqs-promotions] source ack failed', ['seq' => $seq, 'error' => $e->getMessage()]);

            return false;
        }
        StoqsPromotions::saveState(['promo_source_ack_pending' => false]);

        return true;
    }

    /** The minute job: tell StoqS about a seq it has not been told yet. */
    public static function ackIfPending(): bool
    {
        return self::ready() && self::state()['pending'] && StoqsPromotions::row() !== null && self::ackSource();
    }

    /** A 409 stale answer ({source, seq, changed_at, by}): take StoqS's state as ours. */
    public static function adopt(array $answer): void
    {
        if (isset($answer['seq'], $answer['source'])) {
            self::apply((string) $answer['source'], (int) $answer['seq'], 'pull', $answer['by'] ?? null, $answer['note'] ?? null, $answer['changed_at'] ?? null, true);
        }
    }

    private static function remember(int $seq, ?string $by, ?string $note, ?string $changedAt, bool $pending): void
    {
        try {
            $at = $changedAt ? Carbon::parse($changedAt) : now();
        } catch (\Throwable) {
            $at = now();
        }
        StoqsPromotions::saveState([
            'promo_source_seq' => $seq, 'promo_source_changed_at' => $at,
            'promo_source_by' => $by !== null && $by !== '' ? mb_substr($by, 0, 80) : null,
            'promo_source_note' => $note !== null && $note !== '' ? mb_substr($note, 0, 200) : null,
            'promo_source_ack_pending' => $pending,
        ]);
    }

    private static function log(int $seq, ?string $from, string $to, string $origin, ?string $by, ?string $note): void
    {
        DB::table('stoqs_promo_source_log')->insert([
            'seq' => $seq, 'from_source' => $from, 'to_source' => $to, 'origin' => $origin,
            'by_name' => $by !== null && $by !== '' ? mb_substr($by, 0, 80) : null,
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 200) : null,
            'confirmed' => true, 'created_at' => now(),
        ]);
        StockkeeepingLog::record(StockkeeepingLog::TYPE_SITES, 'in', 'promo_source', null,
            ['seq' => $seq, 'from' => $from, 'to' => $to, 'origin' => $origin, 'by' => $by],
            source: StockkeeepingLog::SOURCE_AUTO,
            summary: 'مدیریت تخفیف‌ها: '.($to === 'stoqs' ? 'با StoqS' : 'با فروشگاه').' (شمارهٔ '.$seq.')');
    }
}
