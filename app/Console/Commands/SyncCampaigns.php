<?php

namespace App\Console\Commands;

use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Collection as ShopCollection;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes the campaign plan in config/campaigns.php to the database: discount
 * rules (customer-facing label as the name, `campaign:<key>` in the description,
 * dated in Tehran days), coupons, and free-size bundle
 * products priced off their parts' live prices. Idempotent; runs daily so
 * bundle prices follow StoqS price changes. Rules dropped from the config are
 * switched off, never deleted.
 */
class SyncCampaigns extends Command
{
    protected $signature = 'campaigns:sync';

    protected $description = 'Sync campaign discount rules, coupons and bundles from config/campaigns.php';

    public function handle(): int
    {
        DB::transaction(function () {
            $this->rules();
            $this->coupons();
            $this->bundles();
        });

        return self::SUCCESS;
    }

    private function rules(): void
    {
        $keys = [];
        foreach (config('campaigns.rules', []) as $key => $r) {
            $keys[] = $key;
            $rule = DiscountRule::where('description', "campaign:$key")->first() ?? new DiscountRule;
            $rule->fill([
                'name' => $r['label'],
                'description' => "campaign:$key",
                'priority' => $r['priority'] ?? 10,
                'conditions' => array_map(fn ($c) => $this->resolve($c), $r['conditions'] ?? []),
                'actions' => array_map(fn ($a) => ['type' => $a[0], 'value' => $a[1] ?? 0], $r['actions']),
                'apply_mode' => DiscountRule::APPLY_MODE_ALL,
                'stack_mode' => DiscountRule::STACK_MODE_ALL,
                'starts_at' => empty($r['from']) ? null : $this->tehran($r['from'])->startOfDay()->setTimezone(config('app.timezone')),
                'expires_at' => empty($r['to']) ? null : $this->tehran($r['to'])->endOfDay()->setTimezone(config('app.timezone')),
                'is_active' => true,
            ])->save();
        }

        DiscountRule::where('description', 'like', 'campaign:%')->where('is_active', true)->get()
            ->reject(fn ($r) => in_array(substr($r->description, 9), $keys, true))
            ->each->update(['is_active' => false]);
        $this->info(count($keys).' rules synced');
    }

    /** Condition with slugs (categories/products/collections) turned into ids. */
    private function resolve(array $c): array
    {
        $out = ['type' => $c[0]] + array_diff_key($c, [0 => 1, 'categories' => 1, 'products' => 1, 'collections' => 1]);
        if (isset($c['categories'])) {
            $out['category_ids'] = Category::whereIn('slug', $c['categories'])->pluck('id')->all();
        }
        if (isset($c['products'])) {
            $out['product_ids'] = Product::whereIn('slug', $c['products'])->pluck('id')->all();
        }
        if (isset($c['collections'])) {
            $out['collection_ids'] = ShopCollection::whereIn('slug', $c['collections'])->pluck('id')->all();
        }

        return $out;
    }

    private function coupons(): void
    {
        foreach (config('campaigns.coupons', []) as $code => $c) {
            foreach (['starts_at', 'expires_at'] as $k) {
                if (isset($c[$k])) {
                    $c[$k] = $this->tehran($c[$k])->setTimezone(config('app.timezone'));
                }
            }
            Coupon::updateOrCreate(['code' => $code], $c + ['is_active' => true]);
        }
    }

    /** Config dates are Tehran wall-clock time; the DB stores app-timezone time. */
    private function tehran(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Tehran');
    }

    private function bundles(): void
    {
        foreach (config('campaigns.bundles', []) as $slug => $b) {
            $parts = collect($b['items'])->map(function ($i) {
                $p = Product::active()->where('slug', $i[0])->with(['variants', 'images'])->first();
                $v = $p?->variants->where('is_active', true)
                    ->filter(fn ($v) => empty($i['color']) || $v->color === $i['color'])
                    ->sortByDesc('stock_qty')->first();

                return $v ? ['product' => $p, 'variant' => $v, 'qty' => $i[1] ?? 1] : null;
            });

            $bundle = Product::where('slug', $slug)->first();
            if ($parts->contains(null)) {
                $bundle?->update(['is_active' => false]);
                $this->warn("bundle $slug: a part is missing or inactive, bundle hidden");

                continue;
            }

            $full = $parts->sum(fn ($x) => $x['variant']->effectivePrice() * $x['qty']);
            $price = (int) (floor($full * (100 - $b['off']) / 100 / 10000) * 10000);
            $bundle ??= new Product(['slug' => $slug]);
            $bundle->fill([
                'name' => $b['name'],
                'summary' => $b['summary'] ?? null,
                'description' => $b['description'] ?? null,
                'price' => $price,
                'compare_at_price' => $full,
                'is_bundle' => true,
                'is_active' => true,
            ])->save();

            $bundle->bundleItems()->delete();
            $parts->values()->each(fn ($x, $i) => BundleItem::create([
                'bundle_product_id' => $bundle->id,
                'product_variant_id' => $x['variant']->id,
                'quantity' => $x['qty'],
                'position' => $i,
            ]));
            if (! $bundle->variants()->exists()) {
                $bundle->variants()->create(['size' => '—', 'price' => null, 'stock_qty' => 0, 'is_active' => true]);
            }
            if (! $bundle->images()->exists() && ($img = $parts->first()['product']->images->first())) {
                ProductImage::create(['product_id' => $bundle->id, 'path' => $img->path, 'alt' => $b['name'], 'position' => 0, 'is_primary' => true]);
            }
            $this->line("bundle $slug: $price (was $full)");
        }
    }
}
