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
        View::share('site', Schema::hasTable('settings') ? \App\Models\Setting::map() : []);

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
