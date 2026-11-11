<?php

namespace App\Http\Middleware;

use App\Services\StockKeeping\StoqsPromotions;
use App\Support\CampaignPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shop's own coupon and discount-rule admin pages, read-only while StoqS manages this
 * site's promotions: the lists still open (with the banner), but the forms and every write
 * go back to the list with «… در StoqS مدیریت می‌شوند». Nothing changes when the shop runs its
 * own promotions.
 */
class StoqsPromotionsReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StoqsPromotions::managed()) {
            return $next($request);
        }
        $name = (string) $request->route()?->getName();
        $form = str_ends_with($name, '.create') || str_ends_with($name, '.edit');
        if ($request->isMethodSafe() && ! $form) {
            return $next($request);
        }
        $index = preg_replace('/\.[^.]+$/', '.index', $name);

        return redirect()->route($index)->with('error', CampaignPlan::STOQS_LOCKED);
    }
}
