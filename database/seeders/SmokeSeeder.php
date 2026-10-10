<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Smoke-test data: customers, a few clearly-labelled products, and orders across
 * EVERY status (incl. one of each owned by the dev-admin so the account order
 * timeline can be exercised for each state).
 *
 * Run standalone (safe to re-run — it clears its own prior data first):
 *   php artisan db:seed --class=SmokeSeeder
 *
 * NOT wired into DatabaseSeeder, so it never runs on a normal/production seed.
 */
class SmokeSeeder extends Seeder
{
    private const ORDER_PREFIX = 'CH-SMK-';
    private const USER_TAG = 'smoke';            // group column marks smoke users
    private const PRODUCT_SLUG_PREFIX = 'smoke-';

    public function run(): void
    {
        // Product + ProductVariant use Laravel Scout; skip search indexing so the
        // seeder works even when Meilisearch isn't running (SCOUT_DRIVER=meilisearch).
        Product::withoutSyncingToSearch(fn () => ProductVariant::withoutSyncingToSearch(fn () => $this->seed()));
    }

    private function seed(): void
    {
        $this->cleanup();

        $admin = $this->ensureAdmin();
        $customers = $this->makeCustomers();
        $this->makeProducts();

        $variants = ProductVariant::query()->where('is_active', true)->get();
        if ($variants->isEmpty()) {
            $variants = ProductVariant::query()->get();
        }
        if ($variants->isEmpty()) {
            $this->command?->warn('No product variants found — run CatalogSeeder first.');

            return;
        }

        $statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'canceled', 'failed'];
        $n = 1;
        $delivered = collect();

        // One order per status, owned by the dev-admin → test the account timeline.
        foreach ($statuses as $i => $status) {
            $order = $this->makeOrder($n++, $admin, $status, $variants, daysAgo: ($i + 1) * 2);
            if ($status === 'delivered') {
                $delivered->push($order);
            }
        }

        // A spread of orders across smoke customers for admin lists / CRM.
        $spread = ['delivered', 'shipped', 'processing', 'paid', 'pending', 'delivered', 'canceled', 'delivered'];
        foreach ($spread as $i => $status) {
            $customer = $customers[$i % count($customers)];
            $order = $this->makeOrder($n++, $customer, $status, $variants, daysAgo: $i + 1);
            if ($status === 'delivered') {
                $delivered->push($order);
            }
        }

        // A couple of return requests on delivered orders → populate /admin/returns.
        $returns = $this->makeReturns($delivered);

        $this->command?->info("Smoke data ready: {$customers->count()} customers, ".($n - 1)." orders, {$returns} returns.");
        $this->command?->info('Log in as the dev-admin (/dev/login-admin) and open /account/orders to see one order per status.');
    }

    /** @param \Illuminate\Support\Collection<int, Order> $delivered */
    private function makeReturns(\Illuminate\Support\Collection $delivered): int
    {
        $count = 0;
        foreach ($delivered->take(2) as $i => $order) {
            $order->loadMissing('items');
            $items = $order->items->take(1)->map(fn (OrderItem $it) => [
                'order_item_id' => $it->id,
                'product_variant_id' => $it->product_variant_id,
                'stockkeeping_variant_id' => $it->stockkeeping_variant_id,
                'sku' => $it->sku,
                'name' => $it->name,
                'size' => $it->size,
                'quantity' => 1,
            ])->values()->all();

            if (! $items) {
                continue;
            }

            ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'status' => $i === 0 ? ReturnRequest::STATUS_REQUESTED : ReturnRequest::STATUS_APPROVED,
                'reason' => $i === 0 ? 'سایز مناسب نبود، درخواست تعویض دارم.' : 'کالا ایراد جزئی داشت.',
                'items' => $items,
            ]);
            $count++;
        }

        return $count;
    }

    private function cleanup(): void
    {
        $orders = Order::where('number', 'like', self::ORDER_PREFIX.'%')->get();
        $orderIds = $orders->pluck('id');
        Payment::whereIn('order_id', $orderIds)->delete();
        ReturnRequest::whereIn('order_id', $orderIds)->delete();
        foreach ($orders as $o) {
            $o->items()->delete();
            $o->delete();
        }
        Product::where('slug', 'like', self::PRODUCT_SLUG_PREFIX.'%')->each(function (Product $p) {
            $p->variants()->delete();
            $p->delete();
        });
        User::where('group', self::USER_TAG)->delete();
    }

    private function ensureAdmin(): User
    {
        $admin = User::firstOrCreate(
            ['phone' => '09120000000'],
            ['name' => 'مدیر تستی']
        );
        $admin->forceFill(['is_admin' => true, 'phone_verified_at' => now()])->save();

        return $admin;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function makeCustomers(): \Illuminate\Support\Collection
    {
        $names = ['زهرا نظرزاده', 'علی محمدی', 'سارا کریمی', 'رضا احمدی', 'مینا حسینی'];

        return collect($names)->map(function (string $name, int $i) {
            return User::create([
                'name' => $name,
                'phone' => '0912100'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'phone_verified_at' => now(),
                'group' => self::USER_TAG,
            ]);
        });
    }

    private function makeProducts(): void
    {
        $category = Category::where('is_active', true)->first() ?? Category::first();
        $sizes = ['S', 'M', 'L', 'XL'];
        $colors = [['مشکی', '#282828'], ['قرمز', '#CC3333'], ['کرم', '#E0D5D9']];

        for ($i = 1; $i <= 4; $i++) {
            $price = 850000 + $i * 150000;
            $product = Product::create([
                'category_id' => $category?->id,
                'name' => "محصول تستی اسموک {$i}",
                'slug' => self::PRODUCT_SLUG_PREFIX.$i.'-'.Str::random(4),
                'summary' => 'کالای نمونه برای تست — قابل حذف.',
                'description' => '<p>این یک محصول تستی است که توسط SmokeSeeder ساخته شده و برای آزمایش فروشگاه استفاده می‌شود.</p>',
                'price' => $price,
                'compare_at_price' => $i % 2 === 0 ? $price + 200000 : null,
                'is_active' => true,
                'is_featured' => $i === 1,
                'brand' => 'Chiaco',
            ]);

            foreach ($sizes as $si => $s) {
                [$cName, $cHex] = $colors[array_rand($colors)];
                $product->variants()->create([
                    'size' => $s,
                    'color' => $cName,
                    'color_hex' => $cHex,
                    'sku' => 'SMK-'.$i.'-'.$s,
                    'price' => $price,
                    'stock_qty' => random_int(0, 12),
                    'is_active' => true,
                    // Linked to a StoqS variant so returns/sales push the right id.
                    'stockkeeping_variant_id' => 900000 + $i * 10 + $si,
                ]);
            }
        }
    }

    /** @param \Illuminate\Support\Collection<int, ProductVariant> $variants */
    private function makeOrder(int $n, User $user, string $status, \Illuminate\Support\Collection $variants, int $daysAgo): Order
    {
        $method = ShippingMethod::first();
        $placedAt = now()->subDays($daysAgo)->subHours(random_int(0, 20));
        $paidish = in_array($status, ['paid', 'processing', 'shipped', 'delivered'], true);
        // Realistic StoqS state: most paid orders are reported as a sale to StoqS
        // (have a sale id + fulfilment location); some aren't, so the reconciliation
        // "گزارش‌نشده به StoqS" stat is meaningful.
        $reported = $paidish && $n % 3 !== 0;

        $order = Order::create([
            'number' => self::ORDER_PREFIX.$n,
            'user_id' => $user->id,
            'status' => $status,
            'subtotal' => 0,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 0,
            'shipping_method_id' => $method?->id,
            'shipping_method_name' => $method?->name ?? 'پست پیشتاز',
            'stockkeeping_sale_id' => $reported ? 'SALE-'.$n : null,
            'stockkeeping_location' => $reported ? 'shop:1' : null,
            'stockkeeping_location_name' => $reported ? 'فروشگاه مرکزی' : null,
            'shipping_address' => [
                'recipient_name' => $user->name,
                'province' => 'تهران',
                'city' => 'تهران',
                'line' => 'خیابان نمونه، پلاک '.random_int(1, 200),
                'postal_code' => (string) random_int(1000000000, 9999999999),
            ],
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'customer_note' => $n % 3 === 0 ? 'لطفاً قبل از ارسال تماس بگیرید.' : null,
            'placed_at' => $placedAt,
            'paid_at' => in_array($status, ['paid', 'processing', 'shipped', 'delivered'], true) ? $placedAt->copy()->addMinutes(4) : null,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ]);

        $subtotal = 0;
        $picked = $variants->random(min(random_int(1, 3), $variants->count()));
        foreach ($picked as $variant) {
            $qty = random_int(1, 2);
            $unit = (int) ($variant->price ?: 500000);
            $line = $unit * $qty;
            $subtotal += $line;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'stockkeeping_variant_id' => $variant->stockkeeping_variant_id,
                'name' => optional($variant->product)->name ?? 'کالا',
                'size' => $variant->size,
                'color' => $variant->color,
                'sku' => $variant->sku,
                'unit_price' => $unit,
                'quantity' => $qty,
                'line_total' => $line,
            ]);
        }

        $shipping = $subtotal >= 1_000_000 ? 0 : 50_000;
        $discount = $n % 4 === 0 ? (int) round($subtotal * 0.1) : 0;
        $total = max(0, $subtotal + $shipping - $discount);

        $order->update([
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'discount' => $discount,
            'total' => $total,
        ]);

        // Paid-ish orders get a Payment so reconciliation + dashboard revenue show
        // data; alternate settled/unsettled so the settlement filter has both.
        if (in_array($status, ['paid', 'processing', 'shipped', 'delivered'], true)) {
            $paidAt = $order->paid_at ?? now()->subDays($daysAgo);
            Payment::create([
                'order_id' => $order->id,
                'gateway' => $n % 2 === 0 ? 'zarinpal' : 'mellat',
                'amount' => $total,
                'authority' => 'A'.str_pad((string) $n, 8, '0', STR_PAD_LEFT),
                'ref_id' => (string) random_int(100000000, 999999999),
                'card_pan' => '6037********'.random_int(1000, 9999),
                'status' => Payment::STATUS_PAID,
                'meta' => $n % 3 === 0 ? ['settled_at' => $paidAt->copy()->addDay()->toIso8601String()] : [],
                'paid_at' => $paidAt,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);
        }

        return $order;
    }
}
