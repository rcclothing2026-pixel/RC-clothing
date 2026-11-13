<?php

namespace App\Services\StockKeeping;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Links StoqS made (a kit it created from this shop's plan, a product it matched by hand): the
 * shop adopts them, so the local product carries the StoqS id and is not created twice. Adopt
 * only: a product that already has a StoqS id is never pointed somewhere else.
 */
class StoqsLinks
{
    /**
     * One link {product_id, variant_id|null, site_product_id, site_variant_id|null}: sets the
     * StoqS product id on the local product that has none, and the StoqS variant id on the named
     * size (a kit without a named size: its one placeholder). Returns whether anything changed.
     */
    public static function adopt(array $link): bool
    {
        $spid = $link['site_product_id'] ?? null;
        $pid = (string) ($link['product_id'] ?? '');
        if ($pid === '' || $spid === null || ! ctype_digit((string) $spid)) {
            return false;
        }
        $p = Product::whereKey((int) $spid)->first();
        if (! $p) {
            return false;
        }
        $changed = false;
        if ((string) $p->stockkeeping_id === '') {
            $p->forceFill(['stockkeeping_id' => $pid])->saveQuietly();
            $changed = true;
        } elseif ((string) $p->stockkeeping_id !== $pid) {
            return false;   // linked elsewhere already: never re-point
        }
        $vid = (int) ($link['variant_id'] ?? 0);
        if ($vid > 0) {
            $svid = $link['site_variant_id'] ?? null;
            $v = $svid !== null && ctype_digit((string) $svid)
                ? ProductVariant::where('id', (int) $svid)->where('product_id', $p->id)->first()
                : ($p->is_bundle ? $p->variants()->orderBy('id')->first() : null);
            if ($v && empty($v->stockkeeping_variant_id)) {
                $v->stockkeeping_variant_id = $vid;
                $v->saveQuietly();
                $changed = true;
            }
        }

        return $changed;
    }
}
