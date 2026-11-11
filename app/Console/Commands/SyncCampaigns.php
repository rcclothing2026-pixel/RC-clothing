<?php

namespace App\Console\Commands;

use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Collection as ShopCollection;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Campaigns;
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
 *
 * While StoqS manages this site's promotions only the content is written (bundles; the
 * shelves, slides, pop-ups and badges are read from the plan directly): StoqS's rules and
 * codes count instead, and the shop's own rules and coupons are left exactly as they are.
 */
class SyncCampaigns extends Command
{
    protected $signature = 'campaigns:sync';

    protected $description = 'Sync campaign discount rules, coupons and bundles from config/campaigns.php';

    public function handle(): int
    {
        request()->attributes->remove('campaigns.approvals');
        $managed = \App\Services\StockKeeping\StoqsPromotions::managed();
        DB::transaction(function () use ($managed) {
            if (! $managed) {
                $this->rules();
                $this->coupons();
            }
            $this->bundles();
        });
        if ($managed) {
            $this->info('StoqS manages this site\'s promotions: rules and coupons not synced (content only)');
        }

        return self::SUCCESS;
    }

    private function rules(): void
    {
        $keys = [];
        foreach (config('campaigns.rules', []) + \App\Support\ProductSales::rules(config('campaigns', [])) as $key => $r) {
            $keys[] = $key;
            $rule = DiscountRule::where('description', "campaign:$key")->first() ?? new DiscountRule;
            $d = Campaigns::resolve($r);   // null while its NJ Focus campaign is not approved
            $rule->fill([
                'name' => $r['label'],
                'description' => "campaign:$key",
                'priority' => $r['priority'] ?? 10,
                'conditions' => array_map(fn ($c) => \App\Support\CampaignPlan::resolveCondition($c), $r['conditions'] ?? []),
                'actions' => array_map(fn ($a) => ['type' => $a[0], 'value' => $a[1] ?? 0] + \Illuminate\Support\Arr::except($a, [0, 1]), $r['actions']),
                'apply_mode' => $r['apply_mode'] ?? DiscountRule::APPLY_MODE_ALL,
                'stack_mode' => $r['stack_mode'] ?? DiscountRule::STACK_MODE_ALL,
                'max_uses' => $r['max_uses'] ?? null,
                'max_uses_per_user' => $r['max_uses_per_user'] ?? null,
                'exclusive' => (bool) ($r['exclusive'] ?? false),
                'starts_at' => empty($d['from']) ? null : $this->tehran($d['from'])->startOfDay()->setTimezone(config('app.timezone')),
                'expires_at' => empty($d['to']) ? null : $this->tehran($d['to'])->endOfDay()->setTimezone(config('app.timezone')),
                'is_active' => $d !== null,
            ])->save();
        }

        DiscountRule::where('description', 'like', 'campaign:%')->where('is_active', true)->get()
            ->reject(fn ($r) => in_array(substr($r->description, 9), $keys, true))
            ->each->update(['is_active' => false]);
        $this->info(count($keys).' rules synced');
    }

    private function coupons(): void
    {
        foreach (config('campaigns.coupons', []) as $code => $c) {
            $campaign = $c['campaign'] ?? null;
            unset($c['campaign']);
            $shift = $campaign ? Campaigns::shiftDays($campaign) : 0;   // null while unapproved
            foreach (['starts_at', 'expires_at'] as $k) {
                if (isset($c[$k])) {
                    $c[$k] = $this->tehran($c[$k])->addDays($shift ?? 0)->setTimezone(config('app.timezone'));
                }
            }
            $blank = ['description' => null, 'type' => 'percent', 'value' => 0, 'min_subtotal' => 0, 'max_subtotal' => null, 'max_discount' => null,
                'usage_limit' => null, 'per_user_limit' => null, 'first_order_only' => false, 'exclusive' => false, 'starts_at' => null, 'expires_at' => null];
            Coupon::updateOrCreate(['code' => $code], array_intersect_key($c, $blank) + $blank + ['is_active' => $shift !== null]);
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
