<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockKeeping\StoqsApi;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Console\Command;

/**
 * Before StoqS takes this site's promotions over («مدیریت با StoqS»): what would change.
 * Changes nothing in the shop except what the minute job would also do: one GET /promotions
 * (stored as usual) to test the key.
 *
 *  - sizes with their own price that StoqS never set (stoqs_size_price null): they will be
 *    reported to StoqS as priced by the shop (PUT site-price) and keep their price;
 *  - struck prices set by hand: managed mode clears them; they are kept and put back when
 *    the site goes back to its own promotions;
 *  - key scopes: discounts:read (GET /promotions, evaluate, ack) and catalog:write
 *    (site-price), as far as StoqS has told us.
 */
class StoqsPreSwitchCheck extends Command
{
    protected $signature = 'stoqs:pre-switch-check';

    protected $description = 'List what switching this site to StoqS-managed promotions would change';

    public function handle(): int
    {
        $sizes = ProductVariant::with('product:id,name,slug')->whereNotNull('stockkeeping_variant_id')
            ->whereNotNull('price')->whereNull('stoqs_size_price')->orderBy('product_id')->get();
        $this->info('Sizes priced in the shop (reported to StoqS as shop-set): '.$sizes->count());
        if ($sizes->isNotEmpty()) {
            $this->table(['product', 'size', 'colour', 'price'], $sizes->map(fn ($v) => [
                $v->product?->slug ?? $v->product_id, $v->size, $v->color, number_format((int) $v->price),
            ])->all());
        }

        $struck = StoqsPromotions::handSetStruckPrices();
        $this->info('Struck prices set by hand (cleared while managed, restored after): '.count($struck));
        if ($struck) {
            $names = Product::whereIn('id', array_keys($struck))->pluck('slug', 'id');
            $this->table(['product', 'price', 'struck'], collect($struck)->map(fn ($pc, $id) => [
                $names[$id] ?? $id, number_format($pc[0]), number_format($pc[1]),
            ])->values()->all());
        }

        $missing = [];
        if (! config('stockkeeping.enabled')) {
            $this->warn('StoqS is off (stockkeeping.enabled): nothing can be checked against it.');
        } else {
            $pull = StoqsPromotions::pull();
            if (! $pull['ok']) {
                $this->warn('GET /promotions failed: '.($pull['message'] ?? $pull['status']));
            } else {
                $this->line('GET /promotions: ok ('.($pull['managed'] ? 'StoqS manages this site now' : 'the shop runs its promotions').').');
            }
        }
        foreach (['discounts:read', 'catalog:write'] as $scope) {
            if (isset(StoqsApi::missingScopes()[$scope])) {
                $missing[] = $scope;
            }
        }
        if ($missing) {
            $this->error('The key lacks: '.implode(', ', $missing));
        } else {
            $this->info('Key scopes: no missing discounts:read / catalog:write reported by StoqS (catalog:write is only known after a write).');
        }

        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
