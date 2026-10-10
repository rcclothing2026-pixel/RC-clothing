<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Product;
use App\Support\Hero;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Dedicated Hero banner admin — reproduces the reference editor (global
 * settings + slide tabs + products + floaters + live preview). The whole
 * config is stored as a single Setting JSON via App\Support\Hero.
 */
class HeroController extends Controller
{
    public function edit(): View
    {
        return view('admin.hero.edit', ['config' => Hero::config()]);
    }

    /** Render only the hero, for the admin live-preview iframe. */
    public function preview(): View
    {
        return view('admin.hero.preview');
    }

    public function save(Request $request): JsonResponse
    {
        $config = $this->sanitize((array) $request->input('config', []));
        Hero::save($config);

        return response()->json(['ok' => true]);
    }

    public function reset(): JsonResponse
    {
        Hero::save(Hero::defaults());

        return response()->json(['ok' => true, 'config' => Hero::defaults()]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'max:4096']]);
        $media = Media::createFromUploadedFile(
            $request->file('file'), 'hero', null, (int) $request->user()?->id
        );

        return response()->json(['url' => $media->url]);
    }

    /** Search products for the picker; returns name + link + image. */
    public function products(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $results = Product::active()->with('images')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->latest()->take(15)->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'link' => '/product/'.$p->slug,
                'img' => $p->primary_image_url ?? '',
            ]);

        return response()->json(['results' => $results]);
    }

    /** Resolve current catalog images for a set of product slugs (editor preview). */
    public function productImages(Request $request): JsonResponse
    {
        $slugs = array_filter(array_map('trim', explode(',', $request->string('slugs')->toString())));
        $images = Product::whereIn('slug', $slugs)->with('images')->get()
            ->mapWithKeys(fn (Product $p) => [$p->slug => $p->primary_image_url ?? ''])
            ->filter()
            ->all();

        return response()->json(['images' => $images]);
    }

    /** Whitelist/coerce the posted config so only valid data is stored. */
    private function sanitize(array $in): array
    {
        $accent = fn ($v) => in_array($v, Hero::ACCENTS, true) ? $v : 'red';
        $str = fn ($v, $max = 255) => is_string($v) ? mb_substr(trim($v), 0, $max) : '';

        $slides = [];
        foreach ((array) ($in['slides'] ?? []) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $floaters = [];
            foreach (array_slice((array) ($s['floaters'] ?? []), 0, 4) as $f) {
                $fHex = trim((string) ($f['color_hex'] ?? ''));
                $floaters[] = [
                    'color'     => $accent($f['color'] ?? 'red'),
                    'color_hex' => preg_match('/^#[0-9a-fA-F]{6}$/', $fHex) ? $fHex : '',
                    'link'      => $str($f['link'] ?? '#'),
                    'top'       => max(0, min(95, (float) ($f['top']  ?? 10))),
                    'left'      => max(0, min(95, (float) ($f['left'] ?? 20))),
                    'size'      => max(10, min(300, (int) ($f['size'] ?? 70))),
                    'blur'      => max(0, min(20, (float) ($f['blur'] ?? 1.0))),
                ];
            }
            $products = [];
            foreach (array_slice((array) ($s['products'] ?? []), 0, 8) as $p) {
                $products[] = [
                    'name' => $str($p['name'] ?? '', 120),
                    'dims' => $str($p['dims'] ?? '', 40),
                    'no' => $str($p['no'] ?? '', 12),
                    'color' => $accent($p['color'] ?? 'red'),
                    'img' => $str($p['img'] ?? ''),
                    'link' => $str($p['link'] ?? '#'),
                ];
            }
            $motion = in_array($s['motion'] ?? null, Hero::MOTIONS, true) ? $s['motion'] : 'drift';
            $bgHex  = trim((string) ($s['bg_color'] ?? ''));
            $slides[] = [
                'headline'   => $str($s['headline'] ?? '', 120),
                'accent'     => $accent($s['accent'] ?? 'red'),
                'bg_color'   => preg_match('/^#[0-9a-fA-F]{6}$/', $bgHex) ? $bgHex : '',
                'motion'     => $motion,
                'ctaLink'    => $str($s['ctaLink'] ?? '#'),
                'heroImg'    => $str($s['heroImg'] ?? ''),
                'hero_top'   => max(0, min(80, (float) ($s['hero_top']  ?? 5))),
                'hero_left'  => max(0, min(70, (float) ($s['hero_left'] ?? 24))),
                'hero_width' => max(80, min(700, (int) ($s['hero_width'] ?? 400))),
                'floaters'   => $floaters,
                'products'   => $products,
            ];
        }
        if (! $slides) {
            $slides = Hero::defaults()['slides'];
        }

        $rotation = (float) ($in['rotationSeconds'] ?? 5);
        $rotation = max(2, min(15, $rotation));

        $animSpeed = (int) ($in['animationSpeedMs'] ?? 800);
        $animSpeed = max(200, min(2000, $animSpeed));

        return [
            'version' => 1,
            'storeTitle' => $str($in['storeTitle'] ?? 'چیاکو', 80),
            'brandLine' => $str($in['brandLine'] ?? '', 40),
            'logoLink' => $str($in['logoLink'] ?? '#'),
            'avatarLink' => $str($in['avatarLink'] ?? '#'),
            'rotationSeconds' => $rotation,
            'animationSpeedMs' => $animSpeed,
            'autoplay' => filter_var($in['autoplay'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'pauseOnHover' => filter_var($in['pauseOnHover'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'bgPattern' => in_array($in['bgPattern'] ?? 'none', ['none', 'gate', 'quatrefoil'], true) ? $in['bgPattern'] : 'none',
            'toolbar' => [
                'sizes' => $str(($in['toolbar']['sizes'] ?? '#') ?: '#'),
                'edit' => $str(($in['toolbar']['edit'] ?? '#') ?: '#'),
                'account' => $str(($in['toolbar']['account'] ?? '/account') ?: '/account'),
                'cta' => $str($in['toolbar']['cta'] ?? ''),
            ],
            'slides' => $slides,
        ];
    }
}
