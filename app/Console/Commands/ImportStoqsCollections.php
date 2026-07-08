<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pull collections/groups from StoqS and create/update local records.
 *
 *   php artisan stockkeeping:collections
 */
class ImportStoqsCollections extends Command
{
    protected $signature = 'stockkeeping:collections {--source=auto : manual|auto}';

    protected $description = 'Create/update local collections from StoqS';

    public function handle(StockKeepingClient $client): int
    {
        if (! config('stockkeeping.enabled')) {
            $this->info('StoqS integration disabled — nothing to import.');
            return self::SUCCESS;
        }

        $startedAt = Carbon::now();

        try {
            $items = $client->collections();
        } catch (\Throwable $e) {
            $duration = $startedAt->diffInMilliseconds(now());
            $this->error('Collections pull failed: '.$e->getMessage());
            StockkeeepingLog::record(
                StockkeeepingLog::TYPE_COLLECTIONS_IMPORT, 'in', 'full', null, ['error' => $e->getMessage()],
                'failed', null, $e->getMessage(), source: $this->option('source'), durationMs: $duration,
            );
            return self::FAILURE;
        }

        if (empty($items)) {
            $this->info('No collections returned from StoqS.');
            StockkeeepingLog::record(
                StockkeeepingLog::TYPE_COLLECTIONS_IMPORT, 'in', 'full', null, ['imported' => 0],
                source: $this->option('source'), summary: '0 مجموعه',
            );
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($items as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $slug = $this->slug($name);

            Collection::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => (bool) ($row['active'] ?? true)],
            );

            $this->line("  ✓ {$name}");
            $count++;
        }

        $duration = $startedAt->diffInMilliseconds(now());
        $summary = "{$count} مجموعه وارد/به‌روز شد";
        $this->info($summary);

        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_COLLECTIONS_IMPORT, 'in', 'full', null, ['imported' => $count],
            source: $this->option('source'), summary: $summary, durationMs: $duration,
        );

        return self::SUCCESS;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'set-'.substr(md5($name), 0, 8);
        }

        return $base;
    }
}
