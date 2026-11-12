<?php

namespace App\Console\Commands;

use App\Services\StockKeeping\StoqsPromoSource;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Console\Command;

/**
 * Every minute: GET /promotions (If-None-Match, so usually a 304); after a switch between
 * «StoqS» and «the shop», the full catalog import in the new mode (under the lock it shares
 * with the hourly import); then, while managed, the window prices that hold right now (window
 * starts and ends land within a minute even when a webhook is lost), the size prices set in
 * the shop, and the ack of the version applied. The storefront page cache is dropped when
 * prices changed (LiteSpeed, where the shop has it).
 */
class SyncStoqsPromotions extends Command
{
    protected $signature = 'stoqs:promotions-sync';

    protected $description = 'Pull StoqS promotions (v2) and apply the current window prices';

    public function handle(): int
    {
        $pull = StoqsPromotions::pull();
        if ($pull['status'] === 'off') {
            $this->line('StoqS is off.');

            return self::SUCCESS;
        }
        if (! $pull['ok']) {
            $this->warn('Pull failed: '.($pull['message'] ?? $pull['status']));
        }
        StoqsPromoSource::ackIfPending();   // a switch applied but not yet confirmed to StoqS
        $imported = StoqsPromotions::runPendingImport();
        if ($imported) {
            $this->line('Catalog imported again after the switch.');
        }
        if (! StoqsPromotions::managed()) {
            $this->line('Promotions are the shop\'s own (not managed by StoqS).');
            $this->purge($imported || $pull['flipped']);

            return self::SUCCESS;
        }
        $applied = StoqsPromotions::apply();
        $sites = StoqsPromotions::reportSitePrices();
        $acked = StoqsPromotions::ack();
        $this->purge($imported || $pull['flipped'] || $applied['changed'] > 0);
        $this->info(sprintf('v%s: %d products, %d changed; site prices sent %d, failed %d%s%s',
            StoqsPromotions::version() ?? '-', $applied['products'], $applied['changed'], $sites['sent'], $sites['failed'],
            isset($sites['stopped']) ? ' (stopped at HTTP '.$sites['stopped'].')' : '', $acked ? '; acked' : ''));

        return self::SUCCESS;
    }

    private function purge(bool $changed): void
    {
        if ($changed && class_exists(\App\Support\LiteSpeedCache::class)) {
            \App\Support\LiteSpeedCache::purge();
        }
    }
}
