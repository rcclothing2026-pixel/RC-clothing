<?php

namespace App\Services\StockKeeping;

use App\Models\IntegrationEvent;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockkeeepingLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Product content both ways with StoqS: name, description, short description
 * (the shop's «خلاصه» = products.summary), base and per-size prices, the photo album.
 *
 * StoqS → shop: CatalogImporter::importProduct() (hourly import, product.published,
 * product.content_changed) calls pullText() before it saves and pullImages() after.
 * A field with a shop edit still waiting in the outbox is never overwritten.
 *
 * shop → StoqS: the admin product form snapshots the product before saving and
 * captureEdit() queues what changed as one outbox row (IntegrationEvent type
 * product.content) per product. deliver() sends it:
 *   · PATCH /products/{id} with if_match = the content_updated_at last seen. On 409 the
 *     fields StoqS did not change since then are sent again; for fields both sides
 *     changed StoqS wins and the shop takes its value (a note shows on the product).
 *   · PATCH /products/{id} {base_price}, PATCH /variants/{id} {price|null}
 *   · new photos → POST /products/{id}/media, removed → DELETE /media/{id}, then
 *     PUT /products/{id}/media/order.
 * Imports never go through captureEdit(), so nothing StoqS sent is queued back
 * (and StoqS does not send a key its own changes either).
 *
 * One shop product can carry several StoqS products (colours merged by name):
 * products.stockkeeping_id is the primary; the rest are keys of stoqs_meta.p.
 */
class StoqsContent
{
    public const TYPE = 'product.content';

    public const MODES = [
        'auto' => 'دوطرفه (خودکار)',
        'stock_only' => 'فقط موجودی',
        'manual' => 'فقط دستی',
        'ignore' => 'نادیده',
    ];

    public const LABELS = ['name' => 'نام', 'description' => 'توضیحات', 'short_description' => 'خلاصه', 'price' => 'قیمت', 'images' => 'عکس‌ها'];

    public static function mode(Product $p): string
    {
        return isset(self::MODES[$p->stoqs_sync_mode ?? '']) ? $p->stoqs_sync_mode : 'auto';
    }

    public static function linked(Product $p): bool
    {
        return (string) $p->stockkeeping_id !== '';
    }

    /** StoqS product ids behind this shop product, the primary first. */
    public static function pids(Product $p): array
    {
        $ids = array_map('intval', array_keys((array) ($p->stoqs_meta['p'] ?? [])));

        return array_values(array_unique(array_filter(array_merge([(int) $p->stockkeeping_id], $ids))));
    }

    /** The list price: the struck price when the product shows a discount. */
    public static function basePrice(Product $p): int
    {
        if ($p->is_bundle && self::linked($p) && $p->compare_at_price !== null && (int) ($p->stoqs_meta['kit_struck'] ?? 0) === (int) $p->compare_at_price) {
            return (int) $p->price;   // a kit's struck price may be the sum of its parts (display), not its list price
        }
        return (int) ($p->hasDiscount() ? $p->compare_at_price : $p->price);
    }

    /* ================================================================ shop → StoqS */

    /** What captureEdit() compares: call before the admin save. */
    public static function snapshot(Product $p): array
    {
        $p->load(['variants', 'images']);

        return [
            'name' => (string) $p->name,
            'description' => (string) $p->description,
            'short_description' => (string) $p->summary,
            'price' => self::basePrice($p),
            'variants' => $p->variants->mapWithKeys(fn ($v) => [$v->id => $v->price === null ? null : (int) $v->price])->all(),
            'images' => $p->images->sortBy([['position', 'asc'], ['id', 'asc']])->pluck('id')->values()->all(),
        ];
    }

    /** Queue (and try to send) what the admin changed on a linked «auto» product. */
    public static function captureEdit(Product $p, array $before): void
    {
        if (! config('stockkeeping.enabled') || ! self::linked($p) || self::mode($p) !== 'auto') {
            return;
        }
        $after = self::snapshot($p->fresh());
        $fields = [];
        foreach (['name', 'description', 'short_description'] as $f) {
            if (! self::same($before[$f], $after[$f])) {
                $fields[] = $f;
            }
        }
        if ($before['price'] !== $after['price']) {
            $fields[] = 'price';
        }
        if ($before['images'] !== $after['images']) {
            $fields[] = 'images';
        }
        $vids = [];
        foreach ($after['variants'] as $id => $price) {
            if (array_key_exists($id, $before['variants']) && $before['variants'][$id] !== $price) {
                $vids[] = (int) $id;
            }
        }
        if ($fields || $vids) {
            self::send(self::enqueue($p, $fields, $vids));
        }
    }

    /** One outbox row per product: a still-waiting row takes the new fields. */
    public static function enqueue(Product $p, array $fields, array $variantIds = [], bool $force = false): IntegrationEvent
    {
        $ev = self::waiting($p)->first();
        if ($ev) {
            $pl = $ev->payload;
            $pl['fields'] = array_values(array_unique(array_merge((array) ($pl['fields'] ?? []), $fields)));
            $pl['variants'] = array_values(array_unique(array_merge((array) ($pl['variants'] ?? []), $variantIds)));
            $pl['force'] = ($pl['force'] ?? false) || $force;
            $ev->update(['payload' => $pl, 'status' => 'pending', 'available_at' => now()]);

            return $ev;
        }

        return IntegrationEvent::create([
            'type' => self::TYPE, 'status' => 'pending', 'available_at' => now(),
            'payload' => ['product_id' => $p->id, 'name' => $p->name, 'fields' => array_values($fields), 'variants' => array_values($variantIds), 'force' => $force],
        ]);
    }

    /** Everything «ارسال به StoqS» sends: all text, prices of every size, the album. */
    public static function enqueueAll(Product $p): IntegrationEvent
    {
        return self::enqueue($p, ['name', 'description', 'short_description', 'price', 'images'],
            $p->variants()->whereNotNull('stockkeeping_variant_id')->pluck('id')->all(), true);
    }

    /** Try the row now; on failure it stays in the outbox for the 5-minute flush. */
    public static function send(IntegrationEvent $ev): bool
    {
        return app(StockKeepingReporter::class)->deliver($ev);
    }

    /** A failed row stops retrying (status «dead») after this many attempts or this many hours. */
    public const MAX_ATTEMPTS = 5;

    public const MAX_HOURS = 24;

    /** Rows that may still go out: pending, or failed but still young. Only these hold a pull. */
    private static function waiting(Product $p)
    {
        return IntegrationEvent::where('type', self::TYPE)
            ->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($q) => $q->where('status', 'failed')
                ->where('attempts', '<', self::MAX_ATTEMPTS)->where('created_at', '>', now()->subHours(self::MAX_HOURS))))
            ->orderByDesc('id')->get()
            ->filter(fn ($e) => (int) ($e->payload['product_id'] ?? 0) === (int) $p->id)->values();
    }

    /** Whether a failed row has used up its retries. */
    public static function exhausted(IntegrationEvent $ev, int $attempts): bool
    {
        return $attempts >= self::MAX_ATTEMPTS || $ev->created_at?->lt(now()->subHours(self::MAX_HOURS));
    }

    /**
     * Fields this shop changed and has not sent yet ('price' also stands for any
     * size price). An import must not overwrite them with the older StoqS value.
     */
    public static function pending(Product $p): array
    {
        $out = [];
        foreach (self::waiting($p) as $e) {
            $out = array_merge($out, (array) ($e->payload['fields'] ?? []), ($e->payload['variants'] ?? []) ? ['price'] : []);
        }

        return array_values(array_unique($out));
    }

    /** Outbox handler. Throws StoqsApiError to retry; returns a log line ('' = nothing to do). */
    public static function deliver(IntegrationEvent $ev): string
    {
        $pl = (array) $ev->payload;
        $p = Product::with(['variants', 'images'])->find((int) ($pl['product_id'] ?? 0));
        if (! $p || ! self::linked($p)) {
            return '';
        }
        if (empty($pl['force']) && self::mode($p) !== 'auto') {
            return '';
        }
        $pids = self::pids($p);
        $fields = (array) ($pl['fields'] ?? []);
        $done = [];
        $notes = [];

        $text = array_values(array_intersect($fields, ['name', 'description', 'short_description']));
        foreach ($text ? $pids : [] as $i => $spid) {
            $send = [];
            foreach ($text as $f) {
                if ($f !== 'name' || $i === 0) {   // merged colours: the name belongs to the primary
                    $send[$f] = self::local($p, $f);
                }
            }
            [$sent, $note] = self::patchText($p, $spid, $send);
            $done = array_merge($done, array_map(fn ($f) => self::LABELS[$f], $sent));
            if ($note) {
                $notes[] = $note;
            }
        }

        // Prices: the product's list price (the struck one under a sale or the outlet,
        // which only ever live on products.price/compare_at), and each size's own
        // regular price (variants.price, null = follows the product), cheaper sizes too.
        // A size price StoqS set (stoqs_size_price) is StoqS's already and is not sent
        // back; in chiiaco that is also where a StoqS per-size sale sits.
        $vids = (array) ($pl['variants'] ?? []);
        if (in_array('price', $fields, true) || $vids) {
            $base = self::basePrice($p);
            if (in_array('price', $fields, true) && $base > 0) {
                foreach ($pids as $spid) {
                    if ((int) round((float) ($p->stoqs_meta['p'][$spid]['base']['base_price'] ?? -1)) === $base) {
                        continue;
                    }
                    $r = StoqsApi::call('PATCH', "products/$spid", ['base_price' => $base]);
                    self::remember($p, (array) ($r['product'] ?? []));
                    $done[] = 'قیمت پایه';
                }
            }
            foreach ($vids as $vid) {
                $v = $p->variants->firstWhere('id', (int) $vid);
                if (! $v || ! $v->stockkeeping_variant_id
                    || ($v->price !== null && $v->stoqs_size_price !== null && (int) $v->stoqs_size_price === (int) $v->price)) {
                    continue;
                }
                $own = $v->price === null ? null : (int) $v->price;
                StoqsApi::call('PATCH', 'variants/'.(int) $v->stockkeeping_variant_id, ['price' => $own === null || $own === self::pidBase($p, $v) ? null : $own]);
                $done[] = 'قیمت '.($v->size ?: $v->sku);
            }
        }

        if (in_array('images', $fields, true) && $pids) {
            $done = array_merge($done, self::pushImages($p->fresh(), $pids));
        }

        if ($notes) {
            self::setMeta($p->fresh(), fn (array $m) => ['note' => ['text' => implode(' · ', $notes), 'at' => now()->toIso8601String()]] + $m);
        }
        $line = $p->name.' → StoqS: '.($done ? implode('، ', array_unique($done)) : 'بدون تغییر').($notes ? ' · '.implode(' · ', $notes) : '');
        StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PUSH, 'out', (string) $p->stockkeeping_id, null,
            ['product_id' => $p->id, 'fields' => $fields, 'variants' => $vids, 'stoqs_ids' => $pids], $notes ? 'partial' : 'ok',
            source: StockkeeepingLog::SOURCE_AUTO, summary: $line);

        return $line;
    }

    /** @return array{0: string[], 1: ?string} [fields sent, conflict note] */
    private static function patchText(Product $p, int $spid, array $send): array
    {
        $if = (string) ($p->stoqs_meta['p'][$spid]['at'] ?? '');
        try {
            $r = StoqsApi::call('PATCH', "products/$spid", $send + ($if !== '' ? ['if_match' => $if] : []));
            self::remember($p, (array) ($r['product'] ?? []));

            return [array_keys($send), null];
        } catch (StoqsApiError $e) {
            if ($e->status !== 409) {
                throw $e;
            }
        }
        // Changed in StoqS since this shop last saw it: keep our edit where StoqS still
        // has what we last synced; where both changed, StoqS wins and the shop takes it.
        $theirs = (array) ($e->body['error']['product'] ?? $e->body['product'] ?? []);
        $base = (array) ($p->stoqs_meta['p'][$spid]['base'] ?? []);
        $ours = [];
        $kept = [];
        foreach ($send as $f => $v) {
            $now = (string) ($theirs[$f] ?? '');
            if (! array_key_exists($f, $base) || self::same((string) $base[$f], $now)) {
                $ours[$f] = $v;
            } elseif (! self::same($now, (string) $v)) {
                $kept[] = $f;
            }
        }
        if ($ours) {
            $r = StoqsApi::call('PATCH', "products/$spid", $ours + ['if_match' => (string) ($theirs['content_updated_at'] ?? '')]);
            $theirs = (array) ($r['product'] ?? $theirs);
        }
        if ($kept && $theirs) {
            $fresh = $p->fresh();
            foreach ($kept as $f) {
                self::setLocal($fresh, $f, (string) ($theirs[$f] ?? ''));
            }
            $fresh->saveQuietly();
            $p->setRawAttributes($fresh->getAttributes(), true);
        }
        self::remember($p, $theirs);

        return [array_keys($ours), $kept
            ? 'هم‌زمان در StoqS هم عوض شده بود؛ نسخهٔ StoqS برای '.implode('، ', array_map(fn ($f) => self::LABELS[$f], $kept)).' نگه داشته شد'
            : null];
    }

    /** The StoqS base price of the StoqS product a size belongs to. */
    private static function pidBase(Product $p, ProductVariant $v): int
    {
        foreach ((array) ($p->stoqs_meta['p'] ?? []) as $row) {
            if (in_array((int) $v->stockkeeping_variant_id, array_map('intval', (array) ($row['v'] ?? [])), true) && isset($row['base']['base_price'])) {
                return (int) round((float) $row['base']['base_price']);
            }
        }

        return self::basePrice($p);
    }

    /** The shop album (cover first) → StoqS: new photos up, removed ones deleted, then the order. */
    private static function pushImages(Product $p, array $pids): array
    {
        $owner = [];
        foreach ((array) ($p->stoqs_meta['p'] ?? []) as $spid => $row) {
            foreach ((array) ($row['m'] ?? []) as $mid) {
                $owner[(int) $mid] = (int) $spid;
            }
        }
        $primary = $pids[0];
        $done = [];
        $order = [];
        foreach ($p->images()->orderBy('position')->orderBy('id')->get() as $img) {
            $mid = (int) $img->stoqs_media_id;
            $target = $owner[$mid] ?? $primary;
            if (! $mid) {
                $alt = array_filter(['alt' => (string) $img->alt]);
                $file = self::localFile((string) $img->path);
                $r = $file
                    ? StoqsApi::call('POST', "products/$target/media", null, [], $file, $alt)
                    : StoqsApi::call('POST', "products/$target/media", ['url' => url((string) $img->path)] + $alt);
                $im = (array) ($r['image'] ?? []);
                if (empty($im['id'])) {
                    continue;
                }
                $mid = (int) $im['id'];
                $img->forceFill(['stoqs_media_id' => $mid, 'stoqs_sha256' => $im['sha256'] ?? null])->save();
                $done[] = 'عکس تازه';
            }
            $order[$target][] = $mid;
        }
        $kept = array_merge(...array_values($order ?: [[]]));
        foreach ($owner as $mid => $spid) {
            if (! in_array($mid, $kept, true) && in_array($spid, $pids, true)) {
                try {
                    StoqsApi::call('DELETE', "media/$mid");
                } catch (StoqsApiError $e) {
                    if ($e->status !== 404) {
                        throw $e;
                    }
                }
                $done[] = 'حذف عکس';
            }
        }
        $seen = [];
        foreach ($order as $spid => $ids) {
            $r = StoqsApi::call('PUT', "products/$spid/media/order", ['ids' => array_values(array_unique($ids))]);
            $seen[$spid] = array_map(fn ($i) => (int) ($i['id'] ?? 0), (array) ($r['images'] ?? [])) ?: array_values(array_unique($ids));
        }
        self::setMeta($p, function (array $m) use ($pids, $seen) {
            foreach ($pids as $spid) {
                $m['p'][$spid]['m'] = $seen[$spid] ?? [];
            }

            return $m;
        });
        if ($order) {
            $done[] = 'ترتیب آلبوم';
        }

        return $done;
    }

    /** A gallery path on this server's public disk, if the file is here. */
    private static function localFile(string $path): ?string
    {
        $rel = parse_url($path, PHP_URL_PATH) ?: $path;
        if (($at = strpos($rel, '/storage/')) === false) {
            return null;
        }
        $abs = Storage::disk('public')->path(substr($rel, $at + 9));

        return is_file($abs) ? $abs : null;
    }

    /* ================================================================ StoqS → shop */

    /** Text before the importer saves: description + «خلاصه» from the primary StoqS product. */
    public static function pullText(Product $p, array $data, bool $primary, array $pending): void
    {
        if (! $primary) {
            return;
        }
        if (! in_array('description', $pending, true) && ! empty($data['description'])) {
            $p->description = (string) $data['description'];
        }
        if (! in_array('short_description', $pending, true) && ! empty($data['short_description'])) {
            $p->summary = self::plain((string) $data['short_description']);
        }
    }

    /** The album after the importer saved: StoqS photos (in StoqS order), then shop-only ones. */
    public static function pullImages(Product $p, array $data, bool $primary): void
    {
        if (! config('stockkeeping.pull_images')) {
            return;   // off by default: StoqS photos never touch the shop albums
        }
        $spid = (int) ($data['product_id'] ?? 0);
        if (! $spid || ! array_key_exists('images', $data) || ! is_array($data['images'])) {
            return;
        }
        $images = collect($data['images'])->filter(fn ($i) => is_array($i) && ! empty($i['id']) && ! empty($i['url']))
            ->sortBy(fn ($i) => (int) ($i['position'] ?? 0))->values();
        $mine = [];
        foreach ($images as $im) {
            if ($img = self::imageFor($p, $im)) {
                $mine[] = $img->id;
            }
        }
        $ids = $images->map(fn ($i) => (int) $i['id'])->all();
        $gone = array_diff(array_map('intval', (array) ($p->stoqs_meta['p'][$spid]['m'] ?? [])), $ids);
        if ($gone) {
            $p->images()->whereIn('stoqs_media_id', $gone)->delete();
        }
        self::setMeta($p, function (array $m) use ($spid, $ids) {
            $m['p'][$spid]['m'] = $ids;

            return $m;
        });

        $current = $p->images()->orderBy('position')->orderBy('id')->pluck('id')->all();
        $album = $primary
            ? array_values(array_unique(array_merge($mine, $current)))
            : $current;
        foreach ($album as $i => $id) {
            ProductImage::whereKey($id)->update(['position' => $i + 1, 'is_primary' => $i === 0]);
        }
    }

    /** The gallery image for one StoqS photo: reused when the file is the same, else downloaded. */
    private static function imageFor(Product $p, array $im): ?ProductImage
    {
        $mid = (int) $im['id'];
        $sha = (string) ($im['sha256'] ?? '');
        $img = $p->images()->where('stoqs_media_id', $mid)->first();
        if ($img && ($sha === '' || ! $img->stoqs_sha256 || $img->stoqs_sha256 === $sha)) {
            if (! empty($im['alt']) && $img->alt !== $im['alt']) {
                $img->update(['alt' => (string) $im['alt']]);
            }

            return $img;
        }
        if (! $img && $sha !== '' && ($same = $p->images()->where('stoqs_sha256', $sha)->first())) {
            $same->forceFill(['stoqs_media_id' => $mid])->save();   // a photo this shop sent before

            return $same;
        }
        $path = self::download((string) $im['url'], $mid, $sha, $p);
        if (! $path) {
            return $img;   // keep the old file rather than nothing
        }
        $new = $p->images()->create(['path' => $path, 'alt' => (string) ($im['alt'] ?? '') ?: $p->name, 'position' => 9999]);
        $new->forceFill(['stoqs_media_id' => $mid, 'stoqs_sha256' => $sha ?: null])->save();
        $img?->delete();   // an older version of the same StoqS photo

        return $new;
    }

    private static function download(string $url, int $mid, string $sha, Product $p): ?string
    {
        try {
            $r = Http::timeout(60)->get($url);
        } catch (\Throwable $e) {
            $r = null;
        }
        if (! $r || ! $r->successful() || $r->body() === '') {
            StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PULL, 'in', (string) $p->stockkeeping_id, null, ['url' => $url], 'failed',
                error: $r ? 'HTTP '.$r->status() : 'unreachable', source: StockkeeepingLog::SOURCE_AUTO, summary: 'عکس از StoqS دریافت نشد: '.$p->name);

            return null;
        }
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = ['image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][strtok((string) $r->header('Content-Type'), ';')] ?? 'jpg';
        }
        $rel = 'products/stoqs-'.$mid.'-'.substr($sha !== '' ? $sha : md5($r->body()), 0, 10).'.'.$ext;
        Storage::disk('public')->put($rel, $r->body());
        try {
            \App\Support\ImageOptimizer::optimize($rel, 'public');
        } catch (\Throwable $e) {
            // optional WebP siblings only
        }
        $media = Media::createFromStoredFile(Storage::disk('public')->path($rel), $rel, 'products');

        return $media?->url ?? Storage::disk('public')->url($rel);
    }

    /** Remember what StoqS now holds (if_match, 3-way base, sizes, floor price). */
    public static function remember(Product $p, array $sp): void
    {
        $spid = (int) ($sp['product_id'] ?? 0);
        if (! $spid || ! $p->exists) {
            return;
        }
        self::setMeta($p, function (array $m) use ($spid, $sp) {
            $row = (array) ($m['p'][$spid] ?? []);
            if (! empty($sp['content_updated_at'])) {
                $row['at'] = (string) $sp['content_updated_at'];
            }
            $row['base'] = [
                'name' => (string) ($sp['name'] ?? ''), 'description' => (string) ($sp['description'] ?? ''),
                'short_description' => (string) ($sp['short_description'] ?? ''),
                'base_price' => (float) ($sp['base_price'] ?? $row['base']['base_price'] ?? 0),
            ];
            if (isset($sp['variants']) && is_array($sp['variants'])) {
                $row['v'] = array_values(array_filter(array_map(fn ($v) => (int) ($v['variant_id'] ?? 0), $sp['variants'])));
                $floors = array_filter(array_map(fn ($v) => $v['floor_price'] ?? null, $sp['variants']), fn ($f) => $f !== null);
                $row['floor'] = $floors ? (int) ceil((float) max($floors)) : null;
            }
            $m['p'][$spid] = $row;

            return $m;
        });
        $floors = array_filter(array_map(fn ($r) => $r['floor'] ?? null, (array) ($p->stoqs_meta['p'] ?? [])), fn ($f) => $f !== null);
        $floor = $floors ? max($floors) : null;
        if ($p->stoqs_floor_price !== $floor) {
            $p->forceFill(['stoqs_floor_price' => $floor])->saveQuietly();
        }
    }

    private static function setMeta(Product $p, callable $fn): void
    {
        $p->forceFill(['stoqs_meta' => $fn((array) ($p->stoqs_meta ?? []))])->saveQuietly();
    }

    /** «دریافت از StoqS»: re-import this product's StoqS products now, whatever its mode. */
    public static function pull(Product $p): string
    {
        $rows = (array) (StoqsApi::call('GET', 'catalog', null, ['ids' => implode(',', self::pids($p))])['products'] ?? []);
        if (! $rows) {
            return 'این محصول در StoqS پیدا نشد (یا به سایت فرستاده نشده)';
        }
        $primary = (int) $p->stockkeeping_id;
        usort($rows, fn ($a, $b) => ((int) ($b['product_id'] ?? 0) === $primary) <=> ((int) ($a['product_id'] ?? 0) === $primary));
        foreach ($rows as $row) {
            app(CatalogImporter::class)->importProduct((array) $row, true);
        }
        StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PULL, 'in', (string) $p->stockkeeping_id, null, ['products' => count($rows)],
            source: StockkeeepingLog::SOURCE_MANUAL, summary: $p->name.' ← StoqS: نام، توضیحات، قیمت‌ها و عکس‌ها');

        return 'از StoqS دریافت شد';
    }

    /**
     * «ساخت در StoqS»: create a shop-only product in StoqS, then link its sizes by the
     * returned variant ids (and barcodes, for sizes that had none).
     *
     * @return array{ok: bool, message: string}
     */
    public static function createInStoqs(Product $p): array
    {
        if (self::linked($p)) {
            return ['ok' => false, 'message' => '«'.$p->name.'» از قبل به StoqS وصل است'];
        }
        if ($p->is_bundle) {
            return self::createKitInStoqs($p);
        }
        $p->load(['variants', 'categories', 'category']);
        $rows = $p->variants->values();
        if ($rows->isEmpty()) {
            return ['ok' => false, 'message' => '«'.$p->name.'» هیچ سایز/رنگی ندارد'];
        }
        $base = self::basePrice($p);
        $vars = $rows->map(fn ($v) => array_filter([
            'barcode' => preg_match('/^[A-Za-z0-9._-]{1,13}$/', (string) $v->sku) ? (string) $v->sku : null,
            'size' => (string) $v->size, 'color' => (string) $v->color,
            'price' => $v->price !== null && (int) $v->price !== $base ? (int) $v->price : null,
        ], fn ($x) => $x !== null && $x !== ''))->all();
        $cats = $p->categories->pluck('name')->prepend($p->category?->name)->filter()->unique()->values()->all();
        $body = [
            'name' => $p->name, 'base_price' => $base, 'description' => (string) $p->description,
            'short_description' => (string) $p->summary, 'categories' => $cats, 'web_published' => true, 'variants' => $vars,
        ];
        try {
            $r = StoqsApi::call('POST', 'products', $body);
        } catch (StoqsApiError $e) {
            StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PUSH, 'out', null, null, ['product_id' => $p->id, 'body' => $e->body], 'failed',
                error: $e->getMessage(), source: StockkeeepingLog::SOURCE_MANUAL, summary: $p->name.' در StoqS ساخته نشد: '.$e->getMessage());

            return ['ok' => false, 'message' => '«'.$p->name.'» در StoqS ساخته نشد: '.$e->getMessage()];
        }
        $spid = (int) ($r['product_id'] ?? 0);
        foreach ((array) ($r['variants'] ?? []) as $i => $rv) {
            $v = $rows[$i] ?? null;
            if (! $v) {
                continue;
            }
            $v->stockkeeping_variant_id = (int) $rv['variant_id'];
            if ((string) $v->sku === '' && ! empty($rv['barcode']) && ! ProductVariant::where('sku', $rv['barcode'])->exists()) {
                $v->sku = (string) $rv['barcode'];
            }
            $v->saveQuietly();
        }
        $p->forceFill(['stockkeeping_id' => (string) $spid])->saveQuietly();
        self::remember($p, ['product_id' => $spid, 'variants' => $r['variants'] ?? []] + $body);
        if ($p->images()->exists()) {
            self::send(self::enqueue($p, ['images'], [], true));
        }
        StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PUSH, 'out', (string) $spid, null, ['product_id' => $p->id, 'variants' => $r['variants'] ?? []],
            source: StockkeeepingLog::SOURCE_MANUAL, summary: $p->name.' در StoqS ساخته شد (محصول '.$spid.')');

        return ['ok' => true, 'message' => '«'.$p->name.'» در StoqS ساخته شد'];
    }

    /**
     * «ساخت در StoqS» for a kit: POST /products with `bundle` (its components, per kit) and no
     * variants; StoqS makes the one placeholder variant. The kit's list price there is the shop's
     * kit price. Every component must be linked to StoqS (variant id or barcode) first.
     *
     * @return array{ok: bool, message: string}
     */
    private static function createKitInStoqs(Product $p): array
    {
        $p->load(['categories', 'category', 'bundleItems.variant']);
        $items = [];
        foreach ($p->bundleItems as $bi) {
            $c = $bi->variant;
            if ($c && $c->stockkeeping_variant_id) {
                $items[] = ['variant_id' => (int) $c->stockkeeping_variant_id, 'qty' => (int) $bi->quantity];
            } elseif ($c && (string) $c->sku !== '') {
                $items[] = ['barcode' => (string) $c->sku, 'qty' => (int) $bi->quantity];
            } else {
                return ['ok' => false, 'message' => '«'.$p->name.'» جزئی دارد که به StoqS وصل نیست؛ اول آن را به StoqS وصل کنید'];
            }
        }
        if (! $items) {
            return ['ok' => false, 'message' => '«'.$p->name.'» هیچ جزئی ندارد'];
        }
        $cats = $p->categories->pluck('name')->prepend($p->category?->name)->filter()->unique()->values()->all();
        $body = [
            'name' => $p->name, 'base_price' => (int) $p->price, 'description' => (string) $p->description,
            'short_description' => (string) $p->summary, 'categories' => $cats, 'web_published' => true, 'bundle' => ['items' => $items],
        ];
        try {
            $r = StoqsApi::call('POST', 'products', $body);
        } catch (StoqsApiError $e) {
            StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PUSH, 'out', null, null, ['product_id' => $p->id, 'body' => $e->body], 'failed',
                error: $e->getMessage(), source: StockkeeepingLog::SOURCE_MANUAL, summary: $p->name.' در StoqS ساخته نشد: '.$e->getMessage());

            return ['ok' => false, 'message' => '«'.$p->name.'» در StoqS ساخته نشد: '.$e->getMessage()];
        }
        $spid = (int) ($r['product_id'] ?? 0);
        $p->forceFill(['stockkeeping_id' => (string) $spid])->saveQuietly();
        StoqsKits::placeholder($p, ['variants' => $r['variants'] ?? []]);
        self::remember($p, ['product_id' => $spid, 'variants' => $r['variants'] ?? []] + $body);
        StockkeeepingLog::record(StockkeeepingLog::TYPE_CONTENT_PUSH, 'out', (string) $spid, null, ['product_id' => $p->id, 'bundle' => count($items)],
            source: StockkeeepingLog::SOURCE_MANUAL, summary: $p->name.' (کیت) در StoqS ساخته شد (محصول '.$spid.')');

        return ['ok' => true, 'message' => '«'.$p->name.'» در StoqS ساخته شد'];
    }

    /* ---------------------------------------------------------------- helpers */

    private static function local(Product $p, string $f): string
    {
        return (string) match ($f) {
            'name' => $p->name,
            'description' => $p->description,
            default => $p->summary,
        };
    }

    private static function setLocal(Product $p, string $f, string $v): void
    {
        match ($f) {
            'name' => $p->name = $v,
            'description' => $p->description = $v,
            default => $p->summary = self::plain($v),
        };
    }

    /** StoqS short_description is HTML; the shop's «خلاصه» is one plain line. */
    private static function plain(string $html): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 250, '');
    }

    private static function same(?string $a, ?string $b): bool
    {
        $n = fn (?string $s) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $n($a) === $n($b);
    }
}
