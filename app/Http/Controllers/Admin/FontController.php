<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin control over the storefront's typography. Two slots — body text and
 * headings — each map to a CSS custom property the whole site already consumes
 * (--font-sans, --font-display). The admin can pick a family name and/or upload
 * a font file (woff2/woff/ttf/otf); partials/font-overrides.blade.php turns the
 * saved settings into @font-face + :root variable overrides in <head>. Nothing
 * set ⇒ nothing emitted ⇒ the site keeps its built-in Vazirmatn / Mansory.
 */
class FontController extends Controller
{
    /** slot => [family setting key, url setting key, built-in default family] */
    public const SLOTS = [
        'body'    => ['site.font_body_family', 'site.font_body_url', 'Vazirmatn'],
        'heading' => ['site.font_heading_family', 'site.font_heading_url', 'Mansory'],
    ];

    public function edit(): View
    {
        return view('admin.settings.fonts', ['settings' => Setting::map()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'body_family'    => ['nullable', 'string', 'max:80'],
            'heading_family' => ['nullable', 'string', 'max:80'],
            'body_file'      => ['nullable', 'file', 'extensions:woff2,woff,ttf,otf', 'max:4096'],
            'heading_file'   => ['nullable', 'file', 'extensions:woff2,woff,ttf,otf', 'max:4096'],
            // Base (root) text size in px. Because the site is rem-based, this
            // scales ALL text + spacing proportionally. Blank ⇒ keep default 16.
            'base_px'        => ['nullable', 'integer', 'min:10', 'max:32'],
        ], [], [
            'body_file'    => 'فایل فونت متن',
            'heading_file' => 'فایل فونت عنوان‌ها',
        ]);

        $base = $request->input('base_px');
        $values = [
            'site.font_base_px' => ($base === null || $base === '') ? '' : (string) max(10, min(32, (int) $base)),
        ];
        foreach (self::SLOTS as $slot => [$familyKey, $urlKey, $default]) {
            // Reset to the built-in default → clear both settings + drop the upload.
            if ($request->boolean($slot.'_reset')) {
                $this->deleteFile(Setting::get($urlKey));
                $values[$familyKey] = '';
                $values[$urlKey] = '';
                continue;
            }

            $family = self::safeFamily((string) $request->input($slot.'_family'));

            if ($file = $request->file($slot.'_file')) {
                $this->deleteFile(Setting::get($urlKey)); // replace any previous upload
                $path = $file->store('fonts', 'public');
                $values[$urlKey] = Storage::disk('public')->url($path);
                // Derive a family name from the file name when none was typed.
                if ($family === '') {
                    $family = self::safeFamily(Str::of($file->getClientOriginalName())->beforeLast('.')->value())
                        ?: 'CustomFont';
                }
            }

            $values[$familyKey] = $family; // '' ⇒ the view falls back to the default
        }

        Setting::putMany($values);

        return back()->with('success', 'فونت‌ها ذخیره شد.');
    }

    /**
     * Make a family name safe to drop inside a quoted CSS font-family — strip
     * characters that could break out of the "…" string (this value is echoed
     * into a <style> block). Keeps Unicode letters, digits, spaces and hyphens.
     */
    public static function safeFamily(string $name): string
    {
        return trim(preg_replace('/["\'\\\\{};<>\r\n\t]+/', '', $name) ?? '');
    }

    private function deleteFile(?string $url): void
    {
        if (! $url) {
            return;
        }
        $rel = ltrim(str_replace(Storage::url(''), '', $url), '/');
        if ($rel && Storage::disk('public')->exists($rel)) {
            Storage::disk('public')->delete($rel);
        }
    }
}
