<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Form field name (underscored) => [setting key (dotted), max length].
     * Form names can't use dots (Laravel reads them as nested arrays).
     */
    private const FIELDS = [
        'site_store_name' => ['site.store_name', 120],
        'site_tagline' => ['site.tagline', 200],
        'site_contact_phone' => ['site.contact_phone', 40],
        'site_contact_email' => ['site.contact_email', 120],
        'site_address' => ['site.address', 300],
        'site_instagram' => ['site.instagram', 120],
        'site_telegram' => ['site.telegram', 120],
        'site_whatsapp' => ['site.whatsapp', 40],
        'site_enamad_html' => ['site.enamad_html', 4000],
        'site_samandehi_html' => ['site.samandehi_html', 4000],
        'site_seo_title' => ['site.seo_title', 160],
        'site_seo_description' => ['site.seo_description', 300],
        'site_seo_keywords' => ['site.seo_keywords', 300],
        'site_ga4_id' => ['site.ga4_id', 40],
        'site_gtm_id' => ['site.gtm_id', 40],
        'site_google_verification' => ['site.google_verification', 200],
        'site_live_chat_code' => ['site.live_chat_code', 5000],
        'site_footer_opacity' => ['site.footer_opacity', 10],
        'site_giftwrap_enabled' => ['site.giftwrap_enabled', 4],
        'site_giftwrap_per_item_price' => ['site.giftwrap_per_item_price', 16],
        'site_giftwrap_free_over' => ['site.giftwrap_free_over', 16],
        'site_giftwrap_label' => ['site.giftwrap_label', 160],
        'site_footer_statement' => ['site.footer_statement', 500],
    ];

    public function edit(): View
    {
        return view('admin.settings.site', ['settings' => Setting::map()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::FIELDS as $field => [$key, $max]) {
            $rules[$field] = ['nullable', 'string', "max:$max"];
        }
        $request->validate($rules);

        $values = [];
        foreach (self::FIELDS as $field => [$key, $max]) {
            $values[$key] = $request->input($field);
        }

        // Be tolerant of any paste format for the GTM container ID — the head
        // snippet, the noscript snippet, or just the bare id. Extract GTM-XXXX
        // so the layout can render both head + body snippets correctly.
        if (! empty($values['site.gtm_id'])) {
            $raw = (string) $values['site.gtm_id'];
            if (preg_match('/GTM-[A-Z0-9]+/i', $raw, $m)) {
                $values['site.gtm_id'] = strtoupper($m[0]);
            } else {
                $values['site.gtm_id'] = '';
            }
        }

        // Be tolerant of pasted-from-DNS or pasted-from-meta formats for the
        // Search Console token. Google shows it three ways and people copy any:
        //   · plain token              17lH5H1Q7…
        //   · DNS TXT form             google-site-verification=17lH5H1Q7…
        //   · whole meta tag           <meta name="google-site-verification" content="17lH…">
        // Normalize to just the bare token so the rendered meta is correct.
        if (! empty($values['site.google_verification'])) {
            $v = trim((string) $values['site.google_verification']);
            if (preg_match('/content\s*=\s*"([^"]+)"/i', $v, $m)) {
                $v = $m[1];
            }
            $v = preg_replace('/^google-site-verification\s*=\s*/i', '', $v);
            $values['site.google_verification'] = trim($v);
        }

        // Promo bar slides — variable count, stored as one JSON setting.
        // Each row is {text, url}; empty rows drop on save.
        $slidesRaw = $request->input('promo_slides', []);
        $slides = [];
        if (is_array($slidesRaw)) {
            foreach ($slidesRaw as $row) {
                if (! is_array($row)) continue;
                $text = trim((string) ($row['text'] ?? ''));
                if ($text === '') continue;
                $slides[] = [
                    'text' => mb_substr($text, 0, 200),
                    'url'  => mb_substr(trim((string) ($row['url'] ?? '')), 0, 500),
                ];
            }
        }
        $values['site.promo_slides'] = $slides ? json_encode($slides, JSON_UNESCAPED_UNICODE) : '';

        Setting::putMany($values);

        return back()->with('success', 'تنظیمات سایت ذخیره شد.');
    }

    public function uploadFooterImage(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120']]);
        $media = Media::createFromUploadedFile(
            $request->file('image'), 'footer', null, (int) $request->user()?->id
        );
        Setting::put('site.footer_image', $media->url);
        return response()->json(['ok' => true, 'url' => $media->url]);
    }

    public function deleteFooterImage(): JsonResponse
    {
        $current = Setting::get('site.footer_image');
        if ($current) {
            $relPath = str_replace(Storage::url(''), '', $current);
            if (Storage::disk('public')->exists($relPath)) {
                Storage::disk('public')->delete($relPath);
            }
        }
        Setting::put('site.footer_image', null);
        return response()->json(['ok' => true]);
    }
}
