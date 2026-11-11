<?php

namespace App\Console\Commands;

use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Console\Command;

/**
 * Every minute: GET /promotions (If-None-Match, so usually a 304), put the window prices that
 * hold right now on the products (window starts and ends land within a minute even when a
 * webhook is lost), tell StoqS the size prices set in the shop, and ack the version applied.
 * While the site runs its own promotions it only pulls, to notice when StoqS takes them over.
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
        if (! StoqsPromotions::managed()) {
            $this->line('Promotions are the shop\'s own (not managed by StoqS).');

            return self::SUCCESS;
        }
        $applied = StoqsPromotions::apply();
        $sites = StoqsPromotions::reportSitePrices();
        $acked = StoqsPromotions::ack();
        $this->info(sprintf('v%s: %d products, %d changed; site prices sent %d, failed %d%s',
            StoqsPromotions::version() ?? '-', $applied['products'], $applied['changed'], $sites['sent'], $sites['failed'], $acked ? '; acked' : ''));

        return self::SUCCESS;
    }
}
