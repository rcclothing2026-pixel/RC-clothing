<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Admin\SizeGuideController as AdminSizeGuideController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GiftCardController as AdminGiftCardController;
use App\Http\Controllers\Admin\HomeContentController as AdminHomeContentController;
use App\Http\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\HeroController as AdminHeroController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PopupController as AdminPopupController;
use App\Http\Controllers\Admin\ReconciliationController as AdminReconciliationController;
use App\Http\Controllers\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\DiscountRuleController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Admin\StockLogController as AdminStockLogController;
use App\Http\Controllers\Admin\SystemLogController as AdminSystemLogController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\ShippingMethodController as AdminShippingMethodController;
use App\Http\Controllers\Admin\SmsController as AdminSmsController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\ProfileCompletionController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\Admin\SubscriberController as AdminSubscriberController;
use App\Http\Controllers\StoqsWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Language toggle (en/fa) — persists the choice in the session, then returns
// the visitor to the page they were on. Read by App\Http\Middleware\SetLocale.
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, (array) config('app.available_locales', ['en', 'fa']), true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// /gift — interactive gift discovery (page-builder hero + AJAX filter grid).
Route::get('/gift', [GiftController::class, 'index'])->name('gift.index');
Route::get('/gift/surprise', [GiftController::class, 'surprise'])->name('gift.surprise');

// Inbound StoqS webhooks (signed via X-Stoqs-Signature; CSRF-exempt — see bootstrap/app.php).
Route::post('/webhooks/stoqs', StoqsWebhookController::class)->name('webhooks.stoqs');

// Telegram bot webhook (secret in path; CSRF-exempt — see bootstrap/app.php).
Route::post('/webhooks/telegram/{secret}', TelegramWebhookController::class)->name('webhooks.telegram');

// Terminal-free scheduler trigger. Some shared hosts (no SSH/Terminal, wrong CLI
// PHP binary, or disabled proc_open) can't reliably run `artisan schedule:run`
// from a CLI cron. This runs the scheduler IN-PROCESS on a plain web request —
// the exact same working code path as the admin "manual sync" buttons — so the
// host's cron can `wget`/`curl` this URL every minute instead. Guarded by a
// token derived from APP_KEY (App\Support\CronToken); 404s on mismatch. The full
// URL is shown on Admin → اتصال به StoqS when the scheduler looks stale.
Route::get('/cron/run/{token}', function (string $token) {
    abort_unless(hash_equals(\App\Support\CronToken::value(), $token), 404);
    \Illuminate\Support\Facades\Artisan::call('schedule:run');

    return response(
        "OK\nheartbeat=".\Illuminate\Support\Facades\Cache::get('scheduler:last_run')."\n\n".\Illuminate\Support\Facades\Artisan::output(),
        200,
    )->header('Content-Type', 'text/plain; charset=utf-8');
})->name('cron.run');

// Local-only convenience: one-click admin login (never registered outside local).
if (app()->environment('local')) {
    Route::get('/dev/login-admin', function () {
        $user = \App\Models\User::firstOrCreate(
            ['phone' => '09120000000'],
            ['name' => 'Dev Admin'],
        );
        $user->forceFill(['is_admin' => true, 'phone_verified_at' => now()])->save();
        \Illuminate\Support\Facades\Auth::login($user, remember: true);

        return redirect('/admin');
    })->name('dev.login-admin');
}

// Internal brand/design-system reference (BrandBook v01 primitives).
Route::view('/brand', 'brand.index')->name('brand');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/search/suggest', [\App\Http\Controllers\ShopController::class, 'suggest'])->name('shop.suggest');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::post('/stock-notify', [\App\Http\Controllers\StockNotificationController::class, 'store'])->name('stock.notify');

/* --------------------- Content / legal pages -------------------- */
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page')
    ->where('slug', '[A-Za-z0-9\-]+');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/subscribe', [SubscriberController::class, 'store'])->name('subscriber.store');
Route::get('/unsubscribe/{token}', [SubscriberController::class, 'unsubscribe'])->name('subscriber.unsubscribe');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SitemapController::class, 'llms'])->name('llms');

// Inline SVG placeholder imagery (dev/demo; replaced by uploaded photos).
Route::get('/placeholder', PlaceholderController::class)->name('placeholder');

// Thumbnail lookup for StoqS: barcode/product-id → small WebP image URL.
// Token-guarded (shared webhook secret); images stay on chiiaco, StoqS refs them.
Route::get('/api/catalog/thumbnails', \App\Http\Controllers\Api\CatalogImageController::class)->name('api.catalog.thumbnails');

/* ----------------------------- Cart ----------------------------- */
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/gift', [CartController::class, 'applyGift'])->name('cart.gift.apply');
Route::delete('/cart/gift', [CartController::class, 'removeGift'])->name('cart.gift.remove');

/* ----------------------- Auth (SMS OTP) ------------------------- */
Route::middleware('guest')->group(function () {
    Route::get('/login', [OtpLoginController::class, 'show'])->name('login');
    Route::post('/login', [OtpLoginController::class, 'sendOtp'])->name('login.otp');
    Route::get('/login/verify', [OtpLoginController::class, 'showVerify'])->name('login.verify.show');
    Route::post('/login/verify', [OtpLoginController::class, 'verify'])->name('login.verify');
});
Route::post('/logout', [OtpLoginController::class, 'logout'])->middleware('auth')->name('logout');

// One-time profile completion (name + surname) right after first OTP signup.
Route::middleware('auth')->group(function () {
    Route::get('/complete-profile', [ProfileCompletionController::class, 'show'])->name('profile.complete');
    Route::post('/complete-profile', [ProfileCompletionController::class, 'store'])->name('profile.complete.store');
});

/* --------------------------- Account ---------------------------- */
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/account/orders/{order}', [AccountController::class, 'showOrder'])->name('account.orders.show');
    Route::get('/account/orders/{order}/invoice', [AccountController::class, 'invoice'])->name('account.orders.invoice');
    Route::get('/account/orders/{order}/return', [AccountController::class, 'returnForm'])->name('account.orders.return');
    Route::post('/account/orders/{order}/return', [AccountController::class, 'storeReturn'])->name('account.orders.return.store');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');

    Route::get('/account/telegram/connect', [AccountController::class, 'connectTelegram'])->name('account.telegram.connect');
    Route::post('/account/telegram/disconnect', [AccountController::class, 'disconnectTelegram'])->name('account.telegram.disconnect');

    /* -------------------------- Wishlist ------------------------ */
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist', [WishlistController::class, 'clear'])->name('wishlist.clear');

    // Abandoned-cart recovery link
    Route::get('/cart/restore/{token}', [CartController::class, 'restore'])->name('cart.restore');

    /* -------------------------- Checkout ------------------------ */
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
    Route::get('/checkout/cities/{province}', [CheckoutController::class, 'cities'])->name('checkout.cities');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/failed/{order}', [CheckoutController::class, 'failed'])->name('checkout.failed');
});

// Gateway callback (no auth middleware: the user returns from the bank).
Route::get('/checkout/callback/{method}', [CheckoutController::class, 'callback'])->name('checkout.callback');

/* ---------------------------- Admin ----------------------------- */
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::post('/products/bulk', [AdminProductController::class, 'bulk'])->name('products.bulk');
    Route::resource('products', AdminProductController::class)->except('show');
    Route::resource('categories', AdminCategoryController::class)->except('show');
    Route::resource('collections', AdminCollectionController::class)->except('show');
    Route::resource('size-guides', AdminSizeGuideController::class)->except('show')->parameters(['size-guides' => 'sizeGuide']);
    Route::get('/discounts', [DiscountController::class, 'index'])->name('discounts.index');
    Route::resource('coupons', AdminCouponController::class)->except('show');
    Route::resource('discount-rules', DiscountRuleController::class)->except('show');
    Route::get('/gift-cards', [AdminGiftCardController::class, 'index'])->name('gift-cards.index');
    Route::post('/gift-cards', [AdminGiftCardController::class, 'store'])->name('gift-cards.store');
    Route::delete('/gift-cards/{giftCard}', [AdminGiftCardController::class, 'destroy'])->name('gift-cards.destroy');
    Route::resource('shipping', AdminShippingMethodController::class)->except('show')->parameters(['shipping' => 'shipping']);
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/bulk', [AdminOrderController::class, 'bulk'])->name('orders.bulk');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
    Route::post('/orders/{order}/resend-stoqs', [AdminOrderController::class, 'resendStoqs'])->name('orders.resend-stoqs');

    // Income / settlement reconciliation
    Route::get('/reports/reconciliation', [AdminReconciliationController::class, 'index'])->name('reports.reconciliation');
    Route::post('/reports/reconciliation/settle-all', [AdminReconciliationController::class, 'settleAll'])->name('reports.reconciliation.settle-all');
    Route::post('/reports/reconciliation/{payment}/settle', [AdminReconciliationController::class, 'settle'])->name('reports.reconciliation.settle');

    // Customers / CRM + user & admin management
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [AdminCustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [AdminCustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [AdminCustomerController::class, 'edit'])->name('customers.edit');
    Route::patch('/customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy'])->name('customers.destroy');

    // Returns / RMA
    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{return}', [AdminReturnController::class, 'show'])->name('returns.show');
    Route::patch('/returns/{return}', [AdminReturnController::class, 'update'])->name('returns.update');

    // Contact messages
    Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{message}', [AdminMessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{message}/reply', [AdminMessageController::class, 'reply'])->name('messages.reply');
    Route::delete('/messages/{message}', [AdminMessageController::class, 'destroy'])->name('messages.destroy');

    // Email subscribers (newsletter)
    Route::get('/subscribers', [AdminSubscriberController::class, 'index'])->name('subscribers.index');
    Route::delete('/subscribers/{subscriber}', [AdminSubscriberController::class, 'destroy'])->name('subscribers.destroy');
    Route::post('/subscribers/broadcast', [AdminSubscriberController::class, 'broadcast'])->name('subscribers.broadcast');
    Route::get('/subscribers/export', [AdminSubscriberController::class, 'export'])->name('subscribers.export');

    // SMS control panel
    Route::get('/sms', [AdminSmsController::class, 'settings'])->name('sms.settings');
    Route::patch('/sms', [AdminSmsController::class, 'updateSettings'])->name('sms.settings.update');
    Route::get('/sms/compose', [AdminSmsController::class, 'compose'])->name('sms.compose');
    Route::post('/sms/compose', [AdminSmsController::class, 'sendCampaign'])->name('sms.send');
    Route::post('/sms/test', [AdminSmsController::class, 'testSms'])->name('sms.test');
    Route::get('/sms/log', [AdminSmsController::class, 'log'])->name('sms.log');
    // Telegram bot (admin notifications)
    Route::get('/sms/telegram', [AdminSmsController::class, 'telegram'])->name('sms.telegram');
    Route::patch('/sms/telegram', [AdminSmsController::class, 'updateTelegram'])->name('sms.telegram.update');
    Route::post('/sms/telegram/sync', [AdminSmsController::class, 'syncTelegram'])->name('sms.telegram.sync');
    Route::post('/sms/telegram/test', [AdminSmsController::class, 'testTelegram'])->name('sms.telegram.test');
    Route::post('/sms/telegram/webhook', [AdminSmsController::class, 'webhookTelegram'])->name('sms.telegram.webhook');
    Route::post('/sms/telegram/admins/assign', [AdminSmsController::class, 'assignAdmin'])->name('sms.telegram.admins.assign');
    Route::post('/sms/telegram/admins/manual', [AdminSmsController::class, 'addAdminById'])->name('sms.telegram.admins.manual');
    Route::post('/sms/telegram/admins/remove', [AdminSmsController::class, 'removeAdmin'])->name('sms.telegram.admins.remove');
    Route::post('/sms/telegram/events', [AdminSmsController::class, 'updateTelegramEvents'])->name('sms.telegram.events');

    // Payment gateway settings
    Route::get('/settings/payments', [AdminPaymentMethodController::class, 'index'])->name('settings.payments');
    Route::patch('/settings/payments/{method}', [AdminPaymentMethodController::class, 'update'])->name('settings.payments.update');

    // Site settings
    Route::get('/settings/site', [AdminSettingController::class, 'edit'])->name('settings.site');
    Route::patch('/settings/site', [AdminSettingController::class, 'update'])->name('settings.site.update');
    Route::post('/settings/site/footer-image', [AdminSettingController::class, 'uploadFooterImage'])->name('settings.site.footer-image.upload');
    Route::delete('/settings/site/footer-image', [AdminSettingController::class, 'deleteFooterImage'])->name('settings.site.footer-image.delete');

    // Fonts — upload/assign body + heading fonts and base text size
    Route::get('/settings/fonts', [\App\Http\Controllers\Admin\FontController::class, 'edit'])->name('settings.fonts');
    Route::post('/settings/fonts', [\App\Http\Controllers\Admin\FontController::class, 'update'])->name('settings.fonts.update');

    // Homepage content (legacy quick-edit)
    Route::get('/settings/home', [AdminHomeContentController::class, 'edit'])->name('settings.home');
    Route::patch('/settings/home', [AdminHomeContentController::class, 'update'])->name('settings.home.update');

    // Page builder
    Route::post('/pages/upload', [AdminPageController::class, 'upload'])->name('pages.upload');
    Route::post('/pages/preview', [AdminPageController::class, 'preview'])->name('pages.preview');
    Route::post('/pages/preview-block', [AdminPageController::class, 'previewBlock'])->name('pages.previewBlock');
    Route::post('/pages/reset-home', [AdminPageController::class, 'resetHome'])->name('pages.resetHome');
    Route::post('/pages/{page}/apply-template', [AdminPageController::class, 'applyTemplate'])->name('pages.applyTemplate');
    Route::get('/pages/{page}/revisions', [AdminPageController::class, 'revisions'])->name('pages.revisions');
    Route::post('/pages/{page}/revisions/{revision}/restore', [AdminPageController::class, 'restoreRevision'])->name('pages.restoreRevision');

    Route::get('/patterns', [\App\Http\Controllers\Admin\PatternController::class, 'index'])->name('patterns.index');
    Route::post('/patterns', [\App\Http\Controllers\Admin\PatternController::class, 'store'])->name('patterns.store');
    Route::delete('/patterns/{pattern}', [\App\Http\Controllers\Admin\PatternController::class, 'destroy'])->name('patterns.destroy');
    Route::get('/patterns/{pattern}/render', [\App\Http\Controllers\Admin\PatternController::class, 'render'])->name('patterns.render');
    Route::resource('pages', AdminPageController::class)->except('show');

    // Hero banner (dedicated editor)
    Route::get('/hero', [AdminHeroController::class, 'edit'])->name('hero.edit');
    Route::post('/hero', [AdminHeroController::class, 'save'])->name('hero.save');
    Route::post('/hero/upload', [AdminHeroController::class, 'upload'])->name('hero.upload');
    Route::post('/hero/reset', [AdminHeroController::class, 'reset'])->name('hero.reset');
    Route::get('/hero/products', [AdminHeroController::class, 'products'])->name('hero.products');
    Route::get('/hero/product-images', [AdminHeroController::class, 'productImages'])->name('hero.product-images');
    Route::get('/hero/preview', [AdminHeroController::class, 'preview'])->name('hero.preview');

    // Pop-up builder
    Route::resource('popups', AdminPopupController::class)->except('show');

    // Menu manager
    Route::get('/menus', [AdminMenuController::class, 'index'])->name('menus.index');
    Route::post('/menus', [AdminMenuController::class, 'store'])->name('menus.store');
    Route::post('/menus/from-catalog', [AdminMenuController::class, 'fromCatalog'])->name('menus.fromCatalog');
    Route::put('/menus/{menu}', [AdminMenuController::class, 'update'])->name('menus.update');
    Route::delete('/menus/{menu}', [AdminMenuController::class, 'destroy'])->name('menus.destroy');
    Route::post('/menus/{menu}/move', [AdminMenuController::class, 'move'])->name('menus.move');

    // StoqS stock movement log
    Route::get('/stock-log', [AdminStockLogController::class, 'index'])->name('stock-log.index');

    // Application error log viewer (for hosts with no shell/cPanel terminal)
    Route::get('/system-log', [AdminSystemLogController::class, 'index'])->name('system-log.index');
    Route::get('/system-log/download', [AdminSystemLogController::class, 'download'])->name('system-log.download');
    Route::delete('/system-log', [AdminSystemLogController::class, 'clear'])->name('system-log.clear');

    // StoqS integration
    Route::get('/settings/integration', [AdminIntegrationController::class, 'edit'])->name('settings.integration');
    Route::patch('/settings/integration', [AdminIntegrationController::class, 'update'])->name('settings.integration.update');
    Route::post('/settings/integration/locations', [AdminIntegrationController::class, 'locations'])->name('settings.integration.locations');
    Route::post('/settings/integration/import-catalog', [AdminIntegrationController::class, 'importCatalog'])->name('settings.integration.import');
    Route::post('/settings/integration/pull-stock', [AdminIntegrationController::class, 'pullStock'])->name('settings.integration.pull');
    Route::post('/settings/integration/import-collections', [AdminIntegrationController::class, 'importCollections'])->name('settings.integration.collections');
    Route::post('/settings/integration/retry-outbox', [AdminIntegrationController::class, 'retryOutbox'])->name('settings.integration.retry-outbox');
    Route::post('/settings/integration/outbox/{event}/dismiss', [AdminIntegrationController::class, 'dismissOutbox'])->name('settings.integration.dismiss');

    // Media library
    Route::prefix('media')->name('media.')->group(function () {
        Route::get('/', [AdminMediaController::class, 'index'])->name('index');
        Route::post('/upload', [AdminMediaController::class, 'upload'])->name('upload');
        Route::get('/{media}', [AdminMediaController::class, 'show'])->name('show');
        Route::patch('/{media}', [AdminMediaController::class, 'update'])->name('update');
        Route::post('/{media}/crop', [AdminMediaController::class, 'crop'])->name('crop');
        Route::post('/{media}/resize', [AdminMediaController::class, 'resize'])->name('resize');
        Route::post('/{media}/rotate', [AdminMediaController::class, 'rotate'])->name('rotate');
        Route::delete('/{media}', [AdminMediaController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-delete', [AdminMediaController::class, 'bulkDestroy'])->name('bulk-delete');
    });
});
