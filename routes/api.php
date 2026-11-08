<?php

use App\Http\Controllers\Api\IgCatalogController;
use App\Http\Controllers\Api\IgSellLinkController;
use App\Http\Middleware\VerifyIgCatalogKey;
use Illuminate\Support\Facades\Route;

// Live price & stock for the Instagram DM responder, plus the status of the
// purchase links it made. The key check runs first, so without X-Catalog-Key =
// IG_CATALOG_KEY every path here is a plain 404 (and an unset env turns the
// whole API off the same way).
Route::prefix('ig')->middleware([VerifyIgCatalogKey::class, 'throttle:120,1'])->group(function () {
    Route::get('/catalog', [IgCatalogController::class, 'index']);
    Route::get('/search', [IgCatalogController::class, 'search']);
    Route::get('/products/{product}', [IgCatalogController::class, 'show']);
    Route::get('/alternatives/{product}', [IgCatalogController::class, 'alternatives']);
    Route::get('/sell-links/{code}', [IgSellLinkController::class, 'show'])->where('code', '[A-Za-z0-9]{4,12}');
});

// Creating a /b/{code} purchase link (it only prepares a cart). Its own, tighter
// budget: the prefix keeps it a separate counter from the reads above, which
// would otherwise share one per-IP key.
Route::prefix('ig')->middleware([VerifyIgCatalogKey::class, 'throttle:30,1,ig-sell-links'])->group(function () {
    Route::post('/sell-links', [IgSellLinkController::class, 'store']);
});

// NJ Focus campaign approvals (same key as the Instagram catalogue).
Route::prefix('campaigns')->middleware([VerifyIgCatalogKey::class, 'throttle:30,1,campaigns'])->group(function () {
    Route::get('/plan', [\App\Http\Controllers\Api\CampaignApiController::class, 'plan']);
    Route::post('/approvals', [\App\Http\Controllers\Api\CampaignApiController::class, 'approvals']);
    Route::get('/editor', [\App\Http\Controllers\Api\CampaignApiController::class, 'editor']);
    Route::put('/editor', [\App\Http\Controllers\Api\CampaignApiController::class, 'save']);
    Route::post('/editor/reset', [\App\Http\Controllers\Api\CampaignApiController::class, 'reset']);
    Route::post('/discounts/rules', [\App\Http\Controllers\Api\CampaignApiController::class, 'createRule']);
    Route::put('/discounts/rules/{rule}', [\App\Http\Controllers\Api\CampaignApiController::class, 'updateRule']);
    Route::delete('/discounts/rules/{rule}', [\App\Http\Controllers\Api\CampaignApiController::class, 'deleteRule']);
    Route::post('/discounts/coupons', [\App\Http\Controllers\Api\CampaignApiController::class, 'createCoupon']);
    Route::put('/discounts/coupons/{coupon}', [\App\Http\Controllers\Api\CampaignApiController::class, 'updateCoupon']);
    Route::delete('/discounts/coupons/{coupon}', [\App\Http\Controllers\Api\CampaignApiController::class, 'deleteCoupon']);
});
