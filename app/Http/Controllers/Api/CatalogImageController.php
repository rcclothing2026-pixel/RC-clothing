<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only thumbnail lookup for StoqS. Given barcodes (variant SKUs) and/or
 * StoqS product ids, returns { key => small-webp-image-url }. Guarded by the
 * shared webhook secret so only StoqS can call it. Images stay on chiiaco; the
 * POS references these public, hard-cached URLs directly — nothing is copied.
 *
 *   GET /api/catalog/thumbnails?barcodes=1001,1002&product_ids=55,56
 *   Header: X-Stoqs-Token: <webhook_secret>   (or ?token=…)
 */
class CatalogImageController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('stockkeeping.webhook_secret');
        $token = (string) ($request->header('X-Stoqs-Token') ?: $request->query('token', ''));
        if ($secret === '' || ! hash_equals($secret, $token)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $split = fn ($v) => array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $v)))), 0, 500);
        $barcodes = $split($request->query('barcodes', ''));
        $productIds = $split($request->query('product_ids', ''));

        $out = [];

        if ($barcodes) {
            ProductVariant::whereIn('sku', $barcodes)
                ->with('product.images')
                ->get()
                ->each(function (ProductVariant $v) use (&$out) {
                    $url = $v->product?->primary_image_url;
                    if ($url && $v->sku !== null && ! isset($out[(string) $v->sku])) {
                        $out[(string) $v->sku] = ImageOptimizer::thumbUrl($url);
                    }
                });
        }

        if ($productIds) {
            Product::whereIn('stockkeeping_id', $productIds)
                ->with('images')
                ->get()
                ->each(function (Product $p) use (&$out) {
                    $url = $p->primary_image_url;
                    $key = (string) $p->stockkeeping_id;
                    if ($url && $key !== '' && ! isset($out[$key])) {
                        $out[$key] = ImageOptimizer::thumbUrl($url);
                    }
                });
        }

        return response()->json($out);
    }
}
