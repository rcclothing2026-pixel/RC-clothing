<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];
        $urls[] = ['loc' => route('home'), 'priority' => '1.0'];
        $urls[] = ['loc' => route('shop.index'), 'priority' => '0.9'];

        foreach (['about', 'faq', 'terms', 'privacy', 'shipping-returns', 'size-guide'] as $slug) {
            $urls[] = ['loc' => route('page', $slug), 'priority' => '0.4'];
        }
        $urls[] = ['loc' => route('contact'), 'priority' => '0.5'];

        foreach (Category::where('is_active', true)->get() as $category) {
            $urls[] = ['loc' => route('shop.index', ['category' => $category->slug]), 'priority' => '0.7'];
        }

        foreach (Collection::where('is_active', true)->get() as $collection) {
            $urls[] = ['loc' => route('shop.index', ['collection' => $collection->slug]), 'priority' => '0.6'];
        }

        Product::active()->select('slug', 'updated_at')->get()->each(function ($p) use (&$urls) {
            $urls[] = [
                'loc' => route('product.show', $p->slug),
                'lastmod' => optional($p->updated_at)->toAtomString(),
                'priority' => '0.8',
            ];
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>';
            if (! empty($u['lastmod'])) {
                $xml .= '<lastmod>'.$u['lastmod'].'</lastmod>';
            }
            $xml .= '<priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** robots.txt — crawl rules + sitemap pointer (absolute, domain-agnostic). */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /login',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * llms.txt — a concise, LLM-readable map of the store (see llmstxt.org):
     * title, summary, then the key shopping entry points and categories.
     */
    public function llms(): Response
    {
        $name = Setting::get('site.store_name') ?: 'چیاکو';
        $tagline = Setting::get('site.tagline') ?: 'پوشاک ایرانی با کیفیت؛ طراحی امروزی، دوخت تمیز و ارسال به سراسر کشور.';

        $lines = [
            '# '.$name,
            '',
            '> '.$tagline,
            '',
            '## صفحه‌های اصلی',
            '- [فروشگاه]('.route('shop.index').'): مرور و خرید همه محصولات',
            '- [درباره ما]('.route('page', 'about').')',
            '- [تماس با ما]('.route('contact').')',
            '- [سوالات متداول]('.route('page', 'faq').')',
            '- [شرایط ارسال و بازگشت]('.route('page', 'shipping-returns').')',
            '- [راهنمای سایز]('.route('page', 'size-guide').')',
            '- [قوانین و مقررات]('.route('page', 'terms').')',
            '- [حریم خصوصی]('.route('page', 'privacy').')',
        ];

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        if ($categories->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## دسته‌بندی‌ها';
            foreach ($categories as $category) {
                $lines[] = '- ['.$category->name.']('.route('shop.index', ['category' => $category->slug]).')';
            }
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
