<?php

namespace App\Console\Commands;

use App\Services\StockKeeping\StockKeepingReporter;
use Illuminate\Console\Command;

class FlushStockKeepingEvents extends Command
{
    protected $signature = 'stockkeeping:flush {--limit=100}';

    protected $description = 'Deliver pending/failed stock-keeping integration events (sales, income, CRM).';

    public function handle(StockKeepingReporter $reporter): int
    {
        $reporter->flushPending((int) $this->option('limit'));
        $this->info('Stock-keeping outbox flushed.');

        return self::SUCCESS;
    }
}
