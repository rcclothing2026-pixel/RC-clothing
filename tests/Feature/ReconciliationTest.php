<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function paidOrder(string $number, int $amount, bool $reported): Payment
    {
        $order = Order::create([
            'number' => $number, 'status' => Order::STATUS_PAID, 'subtotal' => $amount,
            'shipping_cost' => 0, 'discount' => 0, 'total' => $amount,
            'customer_name' => 'ت', 'customer_phone' => '09120000000',
            'stockkeeping_sale_id' => $reported ? 'SK-1' : null,
        ]);

        return Payment::create([
            'order_id' => $order->id, 'gateway' => 'zarinpal', 'amount' => $amount,
            'authority' => 'A-'.$number, 'ref_id' => 'R-'.$number,
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);
    }

    public function test_reconciliation_page_lists_income_and_flags_unreported(): void
    {
        $this->paidOrder('CH-R-1', 50_000, true);
        $this->paidOrder('CH-R-2', 30_000, false); // not reported to StoqS

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.reports.reconciliation'))
            ->assertOk()
            ->assertSee('تسویه و مغایرت‌گیری')
            ->assertSee('CH-R-1')
            ->assertSee('گزارش‌نشده'); // the unreported order is flagged
    }

    public function test_settle_marks_payment_settled(): void
    {
        $payment = $this->paidOrder('CH-R-3', 40_000, true);
        $this->assertFalse($payment->isSettled());

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.reports.reconciliation.settle', $payment), ['settlement_ref' => 'BATCH-9'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertTrue($payment->isSettled());
        $this->assertSame('BATCH-9', $payment->meta['settlement_ref']);
    }

    public function test_settle_all_settles_filtered_unsettled_payments(): void
    {
        $this->paidOrder('CH-R-4', 10_000, true);
        $this->paidOrder('CH-R-5', 20_000, true);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.reports.reconciliation.settle-all'))
            ->assertRedirect();

        $this->assertSame(2, Payment::query()->whereNotNull('meta->settled_at')->count());
    }
}
