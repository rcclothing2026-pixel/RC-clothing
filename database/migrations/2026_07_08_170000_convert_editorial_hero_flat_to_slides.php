<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Fold legacy flat editorial_hero fields (image/title/cta on the block itself)
 * into the canonical `slides` array, so the hero is fully editable in the page
 * builder (the editor only exposes the Slides repeater). Renders identically —
 * this mirrors the back-compat mapping in blocks/editorial_hero.blade.php.
 * Idempotent: skips blocks that already have real slides.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        foreach (Page::all() as $page) {
            $blocks = $page->blocks ?? [];
            $changed = false;

            foreach ($blocks as $i => $block) {
                if (($block['type'] ?? null) !== 'editorial_hero') {
                    continue;
                }
                $data = (array) ($block['data'] ?? []);
                $slides = $data['slides'] ?? [];
                $hasSlides = is_array($slides) && count(array_filter(
                    $slides,
                    fn ($s) => is_array($s) && (! empty($s['image']) || ! empty($s['title']))
                ));
                if ($hasSlides) {
                    continue; // already migrated / real slides present
                }
                if (empty($data['image']) && empty($data['title'])) {
                    continue; // nothing to fold
                }

                $data['slides'] = [[
                    'image' => $data['image'] ?? '',
                    'image_mobile' => $data['image_mobile'] ?? '',
                    'kicker' => $data['kicker'] ?? '',
                    'title' => $data['title'] ?? '',
                    'subtitle' => $data['subtitle'] ?? '',
                    'cta_text' => $data['cta_text'] ?? '',
                    'cta_link' => $data['cta_link'] ?? '',
                    'ends_at' => $data['ends_at'] ?? '',
                    'text_color' => $data['text_color'] ?? 'white',
                    'title_size' => $data['title_size'] ?? 'xl',
                    'position_desktop' => $data['text_position'] ?? 'mc',
                    'position_mobile' => $data['text_position'] ?? 'mc',
                ]];

                // Remove the flat keys now living inside the slide; block-level
                // keys (height, overlay, bg_*, overlay_png*, interval) stay put.
                foreach (['image', 'image_mobile', 'kicker', 'title', 'subtitle', 'cta_text', 'cta_link', 'ends_at', 'text_color', 'text_position', 'title_size'] as $k) {
                    unset($data[$k]);
                }

                $blocks[$i]['data'] = $data;
                $changed = true;
            }

            if ($changed) {
                $page->blocks = $blocks;
                $page->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        // One-way content normalisation.
    }
};
