<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReturnController;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReturnRestockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_received_return_restocks_local_inventory(): void
    {
        $variant = ProductVariant::query()->first();
        $before = $variant->stock_qty;

        $order = Order::create([
            'number' => 'CH-T-1', 'status' => 'delivered', 'subtotal' => 1,
            'shipping_cost' => 0, 'discount' => 0, 'total' => 1,
            'customer_name' => 'ت', 'customer_phone' => '09120000000',
        ]);
        $return = ReturnRequest::create([
            'order_id' => $order->id, 'status' => 'requested', 'reason' => 'تست',
            'items' => [['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'quantity' => 2, 'name' => 'x']],
        ]);

        app(ReturnController::class)->update(
            new Request(['status' => 'received']),
            $return,
            app(StockKeepingClient::class),
        );

        $this->assertSame($before + 2, $variant->fresh()->stock_qty);
        $this->assertSame('received', $return->fresh()->status);
    }
}
