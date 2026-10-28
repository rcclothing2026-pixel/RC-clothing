<?php

use App\Http\Controllers\Api\IgCatalogController;
use App\Http\Middleware\VerifyIgCatalogKey;
use Illuminate\Support\Facades\Route;

// Live price & stock for the Instagram DM responder. Read-only. The key check
// runs first, so without X-Catalog-Key = IG_CATALOG_KEY every path here is a
// plain 404 (and an unset env turns the whole API off the same way).
Route::prefix('ig')->middleware([VerifyIgCatalogKey::class, 'throttle:120,1'])->group(function () {
    Route::get('/catalog', [IgCatalogController::class, 'index']);
    Route::get('/search', [IgCatalogController::class, 'search']);
    Route::get('/products/{product}', [IgCatalogController::class, 'show']);
    Route::get('/alternatives/{product}', [IgCatalogController::class, 'alternatives']);
});
