<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Static content / legal pages (about, contact info, FAQ, terms, privacy,
 * shipping & returns). Each maps to resources/views/pages/<slug>.blade.php.
 */
class PageController extends Controller
{
    private const PAGES = [
        'about' => 'درباره چیاکو',
        'faq' => 'سوالات متداول',
        'terms' => 'قوانین و مقررات',
        'privacy' => 'حریم خصوصی',
        'shipping-returns' => 'شرایط ارسال و بازگشت کالا',
        'size-guide' => 'راهنمای سایز',
    ];

    public function show(string $slug): View
    {
        // Page-builder pages take precedence, so the admin can publish new
        // landing pages (or override a static one) at /page/{slug}.
        if ($page = Page::query()->published()->where('is_home', false)->where('slug', $slug)->first()) {
            return view('page', ['page' => $page]);
        }

        if (! isset(self::PAGES[$slug]) || ! ViewFactory::exists("pages.$slug")) {
            throw new NotFoundHttpException;
        }

        return view("pages.$slug", ['pageTitle' => self::PAGES[$slug]]);
    }
}
