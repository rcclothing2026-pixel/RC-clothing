<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\Cart\Cart;
use App\Services\StockKeeping\HttpStockKeepingClient;
use App\Services\StockKeeping\NullStockKeepingClient;
use App\Services\StockKeeping\ResilientStockKeepingClient;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the stock-keeping integration seam. Swaps to the real HTTP
        // client by setting STOCKKEEPING_ENABLED=true once the API is wired.
        $this->app->singleton(StockKeepingClient::class, function () {
            if (! config('stockkeeping.enabled')) {
                return new NullStockKeepingClient;
            }

            $http = new HttpStockKeepingClient(
                baseUrl: (string) config('stockkeeping.base_url'),
                apiKey: (string) config('stockkeeping.api_key'),
                locations: (array) config('stockkeeping.locations', []),
                fulfillmentLocation: config('stockkeeping.fulfillment_location'),
            );

            return new ResilientStockKeepingClient(
                inner: $http,
                cacheTtl: (int) config('stockkeeping.cache_ttl', 120),
                cbEnabled: (bool) config('stockkeeping.circuit_breaker.enabled', true),
                failureThreshold: (int) config('stockkeeping.circuit_breaker.failure_threshold', 5),
                resetTimeout: (int) config('stockkeeping.circuit_breaker.reset_timeout', 60),
            );
        });

        // SMS goes through App\Services\Sms\SmsService (Melipayamak only). It is a
        // plain, self-contained service — no container binding needed.

        // Session-backed cart, resolved lazily within a request.
        $this->app->scoped(Cart::class, fn ($app) => new Cart($app['session.store']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forward Log::error()/critical() to the NJ Focus hub (not just uncaught
        // exceptions). Attaches to the default channel's Monolog logger; guarded
        // so a non-Monolog logger or empty key is a silent no-op.
        if (config('services.nj_focus.key')) {
            try {
                $logger = \Illuminate\Support\Facades\Log::getLogger();
                if ($logger instanceof \Monolog\Logger) {
                    $logger->pushHandler(new \App\Logging\HubLogHandler(\Monolog\Level::Error));
                }
            } catch (\Throwable $e) {
                // no-op
            }
        }

        // Let admin-managed settings override env-based config (stock-keeping + SMS).
        if (Schema::hasTable('settings')) {
            $stored = \App\Models\Setting::map();
            foreach (['enabled', 'base_url', 'api_key', 'locations', 'fulfillment_location', 'webhook_secret', 'import_in_stock_only'] as $k) {
                if (array_key_exists("stockkeeping.$k", $stored)) {
                    config(["stockkeeping.$k" => $stored["stockkeeping.$k"]]);
                }
            }
            // SMS credentials/bodyIds are read directly from settings by
            // App\Services\Sms\SmsService — no config() override needed here.
            foreach (['bot_token', 'bot_username', 'relay_url', 'relay_secret'] as $k) {
                if (! empty($stored["telegram.$k"])) {
                    config(["telegram.$k" => $stored["telegram.$k"]]);
                }
            }
        }

        // Keep the stock-keeping CRM in sync with website accounts.
        \App\Models\User::observe(\App\Observers\UserObserver::class);

        // Broadcast a product card to the Telegram channel the first time it
        // becomes active (idempotent via products.broadcast_at).
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);

        // Fire «back in stock» SMS when a variant's stock_qty crosses 0 → +N.
        \App\Models\ProductVariant::observe(\App\Observers\ProductVariantObserver::class);

        // Snapshot the previous blocks JSON on every Page save so admins
        // can revert from /admin/pages/{page}/revisions.
        \App\Models\Page::observe(\App\Observers\PageObserver::class);

        // Make admin-managed site settings available to every view (footer, meta…).
        $site = Schema::hasTable('settings') ? \App\Models\Setting::map() : [];
        View::share('site', $site);

        // SEO for the storefront layout — canonical, hreflang and JSON-LD are
        // built HERE in PHP (not Blade). Schema keys like "@context"/"@type" and
        // multi-line arrays collide badly with Blade's directive scanner
        // (@php/@json), so the layout only echoes these pre-built strings.
        View::composer('layouts.app', function ($view) use ($site) {
            $req = request();
            $locale = app()->getLocale();
            $urlFa = $req->fullUrlWithoutQuery(['lang']);   // Persian = clean URL
            $urlEn = $req->fullUrlWithQuery(['lang' => 'en']);
            $brand = ($site['site.store_name'] ?? null) ?: 'Racket Club';
            $social = array_values(array_filter([
                ! empty($site['site.instagram']) ? 'https://instagram.com/'.ltrim($site['site.instagram'], '@') : null,
                ! empty($site['site.telegram']) ? 'https://t.me/'.ltrim($site['site.telegram'], '@') : null,
                ! empty($site['site.whatsapp']) ? 'https://wa.me/'.preg_replace('/\D/', '', $site['site.whatsapp']) : null,
            ]));
            $org = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $brand,
                'url' => url('/'),
                'logo' => asset('brand/mark-navy.svg'),
                'sameAs' => $social ?: null,
            ]);
            $website = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $brand,
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/shop').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ];
            $view->with([
                'seoCanonical' => $locale === config('app.locale', 'fa') ? $urlFa : $urlEn,
                'seoUrlFa' => $urlFa,
                'seoUrlEn' => $urlEn,
                'seoBrand' => $brand,
                'seoOgLocale' => $locale === 'fa' ? 'fa_IR' : 'en_US',
                'orgJsonLd' => json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'siteJsonLd' => json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        });

        // Share active categories + live cart count with the site header/nav.
        View::composer('partials.header', function ($view) {
            $categories = Schema::hasTable('categories')
                ? Category::where('is_active', true)->orderBy('position')->get()
                : collect();

            $view->with('navCategories', $categories);
            $view->with('cartCount', app(Cart::class)->count());
        });
    }
}
