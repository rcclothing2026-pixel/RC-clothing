<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignApproval;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Services\StockKeeping\StoqsPromotions;
use App\Support\CampaignPlan;
use App\Support\Margin;
use App\Support\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * NJ Focus ↔ shop campaign link (X-Catalog-Key, like /api/ig/*).
 * GET  /api/campaigns/plan       the plan in config/campaigns.php, per campaign, and the kits'
 *                                struck prices as fixed prices (fixed_prices, for StoqS's import)
 * POST /api/campaigns/approvals  the full approval state from NJ Focus; then
 *                                campaigns:sync switches rules and coupons
 */
class CampaignApiController extends Controller
{
    public function plan(Request $request): JsonResponse
    {
        $cfg = config('campaigns');
        $approvals = CampaignApproval::all()->keyBy('key');
        $out = [];
        foreach ($cfg['campaigns'] ?? [] as $key => $c) {
            $of = fn (string $section) => array_values(array_filter($cfg[$section] ?? [], fn ($i) => ($i['campaign'] ?? null) === $key));
            $lines = array_merge(
                array_map(fn ($r) => 'تخفیف: '.$r['label'], $of('rules')),
                array_map(fn ($s) => 'ست: '.$s['name'].' — '.$s['text'], $of('sets')),
                array_map(fn ($s) => 'نوار بالا: '.$s['text'], $of('slides')),
                array_map(fn ($b) => 'روی محصول: '.$b['text'], $of('badges')),
                array_map(fn ($s) => 'صفحه: '.$s['name'], $of('shelves')),
            );
            foreach ($cfg['popups'] ?? [] as $pk => $p) {
                if (($p['campaign'] ?? null) === $key) {
                    $lines[] = 'پاپ‌آپ: '.$pk;
                }
            }
            foreach ($cfg['sales'] ?? [] as $s) {
                if (($s['campaign'] ?? null) === $key) {
                    $lines[] = 'تخفیف محصولی: '.($s['label'] ?? '').' — '.count($s['products'] ?? []).' محصول';
                }
            }
            foreach ($cfg['coupons'] ?? [] as $code => $cp) {
                if (($cp['campaign'] ?? null) === $key) {
                    $lines[] = 'کد تخفیف: '.$code.' — '.($cp['description'] ?? '');
                }
            }
            $a = $approvals->get($key);
            $out[] = [
                'key' => $key, 'name' => $c['name'], 'from' => $c['from'], 'to' => $c['to'], 'items' => $lines,
                'approved' => (bool) $a?->approved, 'approved_from' => $a?->starts_on?->toDateString(), 'approved_to' => $a?->ends_on?->toDateString(),
            ];
        }

        return response()->json(['shop' => $request->getHost(), 'timezone' => 'Asia/Tehran', 'campaigns' => $out, 'fixed_prices' => self::fixedPrices(), 'bundles' => self::bundles()]);
    }

    /**
     * The kits (config campaigns.bundles) at their struck price, as one group of fixed prices for
     * StoqS's «وارد کردن از سایت»: {key, name, items: [{key: slug, name, price, compare_at, active,
     * stoqs_product_ids, components}]}. While StoqS manages the site the catalog import has put
     * the list price back, so the struck price kept at the switch (saved_prices) is the one sent.
     * StoqS maps a kit by its own link (site product = the slug, or the StoqS id the kit carries);
     * the components are listed for the reader only. No kits: an empty list.
     */
    public static function fixedPrices(): array
    {
        $slugs = array_keys((array) config('campaigns.bundles', []));
        if (! $slugs) {
            return [];
        }
        $saved = [];
        try {
            $saved = (array) json_decode((string) (StoqsPromotions::row()->saved_prices ?? ''), true);
        } catch (\Throwable) {
        }
        $kits = Product::whereIn('slug', $slugs)->where('is_bundle', true)->with('bundleItems.variant.product')->get()->keyBy('slug');
        $items = [];
        foreach ($slugs as $slug) {
            $cfg = (array) config('campaigns.bundles.'.$slug);
            $p = $kits->get($slug);
            if (! $p) {
                $items[] = ['key' => $slug, 'name' => (string) ($cfg['name'] ?? $slug), 'price' => null, 'compare_at' => null, 'active' => false, 'stoqs_product_ids' => [], 'components' => []];

                continue;
            }
            [$price, $compare] = isset($saved[$p->id]) && is_array($saved[$p->id]) ? array_map('intval', $saved[$p->id]) : [(int) $p->price, (int) $p->compare_at_price];
            $items[] = [
                'key' => $slug, 'name' => (string) $p->name, 'price' => $price ?: null, 'compare_at' => $compare ?: null, 'active' => (bool) $p->is_active,
                'stoqs_product_ids' => $p->stockkeeping_id ? [(int) $p->stockkeeping_id] : [],
                'components' => $p->bundleItems->map(fn ($i) => ['name' => (string) ($i->variant?->product?->name ?? ''), 'qty' => (int) $i->quantity,
                    'stoqs_product_id' => $i->variant?->product?->stockkeeping_id ? (int) $i->variant->product->stockkeeping_id : null])->values()->all(),
            ];
        }

        return [['key' => 'kits', 'name' => 'قیمت ویژهٔ کیت‌ها', 'items' => $items]];
    }

    /**
     * Every kit of this shop for StoqS's import (the plan answer's `bundles`): {site_product_id (the
     * shop product id), key (slug), name, price, compare_at, active, stoqs_product_id|null,
     * components: [{site_variant_id, stoqs_variant_id|null, qty, name}]}. The price is the kit's own
     * (the struck price kept at a StoqS switch when there is one). fixed_prices keeps its kits too.
     */
    public static function bundles(): array
    {
        $saved = [];
        try {
            $saved = (array) json_decode((string) (StoqsPromotions::row()->saved_prices ?? ''), true);
        } catch (\Throwable) {
        }
        $out = [];
        foreach (Product::where('is_bundle', true)->with('bundleItems.variant.product')->orderBy('id')->get() as $p) {
            [$price, $compare] = isset($saved[$p->id]) && is_array($saved[$p->id]) ? array_map('intval', $saved[$p->id]) : [(int) $p->price, (int) $p->compare_at_price];
            $out[] = [
                'site_product_id' => (string) $p->id, 'key' => (string) $p->slug, 'name' => (string) $p->name,
                'price' => $price, 'compare_at' => $compare ?: null, 'active' => (bool) $p->is_active,
                'stoqs_product_id' => $p->stockkeeping_id ? (int) $p->stockkeeping_id : null,
                'components' => $p->bundleItems->filter(fn ($i) => $i->variant)->map(fn ($i) => [
                    'site_variant_id' => (string) $i->variant->id,
                    'stoqs_variant_id' => $i->variant->stockkeeping_variant_id ? (int) $i->variant->stockkeeping_variant_id : null,
                    'qty' => (int) $i->quantity,
                    'name' => trim(($i->variant->product?->name ?? '').' '.(trim((string) $i->variant->size) !== '—' ? $i->variant->size : '').' '.$i->variant->color),
                ])->values()->all(),
            ];
        }

        return $out;
    }

    public function approvals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaigns' => ['present', 'array'],
            'campaigns.*.key' => ['required', 'string', 'max:64'],
            'campaigns.*.approved' => ['required', 'boolean'],
            'campaigns.*.from' => ['required', 'date_format:Y-m-d'],
            'campaigns.*.to' => ['required', 'date_format:Y-m-d', 'after_or_equal:campaigns.*.from'],
            'campaigns.*.by' => ['nullable', 'string', 'max:120'],
        ]);
        $known = array_keys(config('campaigns.campaigns', []));
        $rows = array_values(array_filter($data['campaigns'], fn ($c) => in_array($c['key'], $known, true)));

        DB::transaction(function () use ($rows) {
            CampaignApproval::whereNotIn('key', array_column($rows, 'key') ?: ['__none__'])->delete();
            foreach ($rows as $c) {
                CampaignApproval::updateOrCreate(['key' => $c['key']], [
                    'starts_on' => $c['from'], 'ends_on' => $c['to'], 'approved' => $c['approved'], 'approved_by' => $c['by'] ?? null,
                ]);
            }
        });
        request()->attributes->remove('campaigns.approvals');
        Artisan::call('campaigns:sync');

        return response()->json(['ok' => true, 'approved' => array_values(array_map(fn ($c) => $c['key'], array_filter($rows, fn ($c) => $c['approved'])))]);
    }

    /** GET /api/campaigns/editor — the whole plan in editor shape, the catalogue and the discounts outside it. */
    public function editor(): JsonResponse
    {
        return response()->json(CampaignPlan::editorPayload());
    }

    /** PUT /api/campaigns/editor — store the edited plan; 422 with the problems in Persian. */
    public function save(Request $request): JsonResponse
    {
        // While StoqS manages the promotions the outlet is StoqS's: refuse before saving anything.
        if ($request->has('outlet') && StoqsPromotions::managed()) {
            return response()->json(['ok' => false, 'errors' => [Outlet::MANAGED]], 422);
        }
        // Costs first: the plan's margin checks read them. Then the plan, then the outlet.
        $errors = $request->has('costs') ? Margin::saveCosts((array) $request->input('costs', [])) : [];
        if (! $errors) {
            $errors = CampaignPlan::save((array) $request->input('plan', []), $request->input('by'));
        }
        if (! $errors && $request->has('outlet')) {
            $errors = Outlet::sync((array) $request->input('outlet', []));
        }
        if ($errors) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true] + CampaignPlan::editorPayload());
    }

    /** GET /api/campaigns/costs — finished cost per product slug (StoqS and NJ Focus). */
    public function costs(): JsonResponse
    {
        $slugs = Product::pluck('slug', 'id');

        return response()->json(['min_margin' => Margin::min(), 'costs' => collect(Margin::costs())
            ->mapWithKeys(fn ($cost, $id) => isset($slugs[$id]) ? [$slugs[$id] => $cost] : [])->all()]);
    }

    /** PUT /api/campaigns/costs {costs: {slug: cost|null}, source?} */
    public function saveCosts(Request $request): JsonResponse
    {
        $errors = Margin::saveCosts((array) $request->input('costs', []), (string) $request->input('source', 'api'));

        return $errors ? response()->json(['ok' => false, 'errors' => $errors], 422) : $this->costs();
    }

    /** POST /api/campaigns/editor/reset — back to config/campaigns.php. */
    public function reset(): JsonResponse
    {
        if ($errors = CampaignPlan::reset()) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true] + CampaignPlan::editorPayload());
    }

    public function createRule(Request $request): JsonResponse
    {
        return $this->result(CampaignPlan::saveRule(null, $request->all()));
    }

    public function updateRule(Request $request, DiscountRule $rule): JsonResponse
    {
        return $this->result(CampaignPlan::saveRule($rule, $request->all()));
    }

    public function deleteRule(DiscountRule $rule): JsonResponse
    {
        return $this->result(CampaignPlan::deleteRule($rule));
    }

    public function createCoupon(Request $request): JsonResponse
    {
        return $this->result(CampaignPlan::createCoupon($request->all()));
    }

    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        return $this->result(CampaignPlan::updateCoupon($coupon, $request->all()));
    }

    public function deleteCoupon(Coupon $coupon): JsonResponse
    {
        return $this->result(CampaignPlan::deleteCoupon($coupon));
    }

    /** 422 with the problems, or the fresh editor payload. */
    private function result(array $errors): JsonResponse
    {
        return $errors ? response()->json(['ok' => false, 'errors' => $errors], 422) : response()->json(['ok' => true] + CampaignPlan::editorPayload());
    }
}
