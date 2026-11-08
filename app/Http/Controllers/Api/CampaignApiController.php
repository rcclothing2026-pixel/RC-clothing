<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignApproval;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Support\CampaignPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * NJ Focus ↔ shop campaign link (X-Catalog-Key, like /api/ig/*).
 * GET  /api/campaigns/plan       the plan in config/campaigns.php, per campaign
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

        return response()->json(['shop' => $request->getHost(), 'timezone' => 'Asia/Tehran', 'campaigns' => $out]);
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
        $errors = CampaignPlan::save((array) $request->input('plan', []), $request->input('by'));
        if ($errors) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true] + CampaignPlan::editorPayload());
    }

    /** POST /api/campaigns/editor/reset — back to config/campaigns.php. */
    public function reset(): JsonResponse
    {
        CampaignPlan::reset();

        return response()->json(['ok' => true] + CampaignPlan::editorPayload());
    }

    public function updateRule(Request $request, DiscountRule $rule): JsonResponse
    {
        $errors = CampaignPlan::updateRule($rule, $request->all());

        return response()->json($errors ? ['ok' => false, 'errors' => $errors] : ['ok' => true], $errors ? 422 : 200);
    }

    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $errors = CampaignPlan::updateCoupon($coupon, $request->all());

        return response()->json($errors ? ['ok' => false, 'errors' => $errors] : ['ok' => true], $errors ? 422 : 200);
    }
}
