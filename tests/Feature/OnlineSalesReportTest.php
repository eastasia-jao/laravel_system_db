<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_report_shows_summaries_png_preview_and_editable_delivery_status(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'ONLINE-REPORT',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'ONLINE-ITEM',
            'name' => 'Online Item',
            'stock' => 20,
            'sales_price' => 100,
            'status' => 'active',
        ]);
        $replacementProduct = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'ONLINE-REPLACEMENT',
            'name' => 'Online Replacement Item',
            'stock' => 5,
            'sales_price' => 275,
            'status' => 'active',
        ]);

        $septemberSale = $this->onlineSale($admin, $hub, $product, [
            'order_number' => 'ONLINE-SEPTEMBER',
            'order_date' => '2026-09-10',
            'mode_of_payment' => 'BANK_TRANSFER',
            'bank_name' => 'BPI',
            'grand_total' => 250,
            'shipping_fee_amount' => 25,
            'delivery_status' => 'not_applicable',
        ]);
        $septemberItem = $septemberSale->items()->first();
        $septemberItem->update([
            'return_status' => 'requested',
            'returned_quantity' => 1,
            'refund_status' => 'pending',
            'customer_refund_amount' => 50,
        ]);
        ProductReplacement::create([
            'transaction_id' => $septemberSale->id,
            'transaction_item_id' => $septemberItem->id,
            'original_product_id' => $product->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'original_unit_price' => 250,
            'replacement_unit_price' => 275,
            'reason' => 'Customer selected a different item.',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $this->onlineSale($admin, $hub, $product, [
            'order_number' => 'ONLINE-OCTOBER',
            'order_date' => '2026-10-10',
            'mode_of_payment' => 'GCASH',
            'grand_total' => 300,
            'delivery_status' => 'shipped',
        ]);
        $this->onlineSale($admin, $hub, $product, [
            'order_number' => 'ONLINE-DATED-CHECK',
            'order_date' => '2026-11-10',
            'mode_of_payment' => 'DATED_CHECK',
            'bank_name' => 'METROBANK',
            'grand_total' => 150,
            'delivery_status' => 'pending',
        ]);
        $this->onlineSale($admin, $hub, $product, [
            'order_number' => 'ONLINE-POST-DATED-CHECK',
            'order_date' => '2026-11-11',
            'mode_of_payment' => 'POST_DATED_CHECK',
            'bank_name' => 'BDO',
            'grand_total' => 175,
            'delivery_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'online',
            'from' => '2026-09-01',
            'to' => '2026-11-30',
        ]));

        $response->assertOk()
            ->assertDontSee('Monthly Sales')
            ->assertSee('Sales by Payment Method')
            ->assertSee('GCASH')
            ->assertSee('PAYMAYA')
            ->assertSee('BDO')
            ->assertSee('BPI')
            ->assertSee('DATED CHECK')
            ->assertSee('POST-DATED CHECK')
            ->assertSee('OTHERS')
            ->assertSee('Shipping ₱')
            ->assertSee('Returns')
            ->assertSee('Replacements')
            ->assertSee('Preview / Save PNG')
            ->assertSee('Save as PNG')
            ->assertSee('id="onlineReportPreviewModal" data-bs-backdrop="static" data-bs-keyboard="false"', false)
            ->assertDontSee('Print / Save PDF')
            ->assertSee('data-online-delivery-form', false)
            ->assertSee('View details')
            ->assertSee('Product items')
            ->assertDontSee('Product subtotal</td>')
            ->assertSee('Online Item')
            ->assertSee('RETURN REQUESTED')
            ->assertSee('Quantity returned: 1')
            ->assertSee('Replacement status')
            ->assertSee('Online Replacement Item')
            ->assertSee('Replacement qty 1')
            ->assertSee('Customer selected a different item.')
            ->assertSee('data-online-preview-only', false)
            ->assertSee('data-online-preview-remove', false)
            ->assertDontSee('TOTAL SALES /')
            ->assertDontSee('Customer details');

        $monthlySales = $response->viewData('monthlySales');
        $this->assertSame(250.0, (float) $monthlySales->get('2026-09')->total);
        $this->assertSame(300.0, (float) $monthlySales->get('2026-10')->total);
        $paymentSales = $response->viewData('onlinePaymentSales');
        $this->assertSame(250.0, (float) $paymentSales->get('BPI'));
        $this->assertSame(300.0, (float) $paymentSales->get('GCASH'));
        $this->assertSame(150.0, (float) $paymentSales->get('DATED CHECK'));
        $this->assertSame(175.0, (float) $paymentSales->get('POST-DATED CHECK'));

        $this->patch(route('sales.status.update', $septemberSale), [
            'delivery_status' => 'delivered',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('delivered', $septemberSale->fresh()->delivery_status);
    }

    public function test_online_refund_requires_complete_returns_and_permanently_locks_the_order(): void
    {
        $hub = StoreHub::create([
            'name' => 'Online Refund Hub',
            'code' => 'ONLINE-REFUND',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'ONLINE-REFUND-ITEM',
            'name' => 'Online Refund Item',
            'stock' => 10,
            'sales_price' => 100,
            'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'customer_name' => 'Online Refund Customer',
            'order_number' => 'ONLINE-REFUND-ORDER',
            'order_date' => '2026-10-21',
            'sub_total' => 200,
            'total_amount' => 200,
            'grand_total' => 200,
            'shipping_fee_amount' => 20,
            'proof_amount' => 220,
            'status' => 'confirmed',
            'payment_status' => 'not_applicable',
            'delivery_status' => 'delivered',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
        ]);
        $reportUrl = route('hub.report', ['hub' => $hub->id, 'channel' => 'online']);
        $refundUrl = route('hub.report.online.refund', [$hub->id, $sale->id]);

        $initialResponse = $this->actingAs($admin)->get($reportUrl);
        $initialResponse->assertOk()
            ->assertSee('Refund is available after every ordered unit is recorded as returned.')
            ->assertSee('id="onlineReportPreviewModal" data-bs-backdrop="static" data-bs-keyboard="false"', false);
        $previewStart = strpos($initialResponse->getContent(), 'id="onlineReportPreviewModal"');
        $previewBody = strpos($initialResponse->getContent(), '<div class="modal-body', $previewStart);
        $this->assertNotFalse($previewStart);
        $this->assertNotFalse($previewBody);
        $this->assertStringNotContainsString('btn-close', substr($initialResponse->getContent(), $previewStart, $previewBody - $previewStart));
        $this->from($reportUrl)->post($refundUrl)
            ->assertRedirect($reportUrl)
            ->assertSessionHasErrors('refund');

        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'online',
            'condition' => 'good',
            'quantity' => 2,
            'occurred_on' => '2026-10-21',
            'created_by' => $admin->id,
        ]);

        $replacementProduct = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'ONLINE-REFUND-REPLACEMENT',
            'name' => 'Online Refund Replacement',
            'stock' => 3,
            'sales_price' => 100,
            'status' => 'active',
        ]);
        $replacement = ProductReplacement::create([
            'transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'original_product_id' => $product->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);
        $this->get($reportUrl)->assertOk()
            ->assertSee('Resolve pending or approved replacements before refunding.');
        $this->from($reportUrl)->post($refundUrl)
            ->assertRedirect($reportUrl)
            ->assertSessionHasErrors('refund');
        $replacement->delete();

        $this->get($reportUrl)->assertOk()->assertSee('Refund &amp; Lock Order', false);
        $this->from($reportUrl)->post($refundUrl)
            ->assertRedirect($reportUrl)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $sale = $sale->fresh();
        $this->assertSame('refunded', $sale->status);
        $this->assertSame('refunded', $sale->payment_status);
        $this->assertSame('0.00', $sale->proof_amount);
        $this->assertSame('0.00', $sale->amount_paid);
        $this->assertSame('0.00', $sale->grand_total);
        $this->assertSame('0.00', $sale->total_amount);
        $this->assertSame('0.00', $sale->sub_total);
        $this->assertSame('200.00', $sale->refund_original_order_value);
        $this->assertSame('220.00', $sale->refund_original_collected);
        $this->assertSame('200.00', $sale->wholesale_refund_amount);
        $this->assertSame($admin->id, $sale->refunded_by);
        $this->assertNotNull($sale->refunded_at);

        $this->get($reportUrl)->assertOk()
            ->assertSee('Refunded and locked')
            ->assertViewHas('totalSales', 0.0)
            ->assertViewHas('metrics', fn (array $metrics) => (float) $metrics['refund_total'] === 200.0);
        $this->patch(route('sales.status.update', $sale->id), [
            'delivery_status' => 'pending',
        ])->assertStatus(409);
        $this->from($reportUrl)->post($refundUrl)
            ->assertRedirect($reportUrl)
            ->assertSessionHasErrors('refund');
    }

    private function onlineSale(User $user, StoreHub $hub, Product $product, array $attributes): SalesTransaction
    {
        $sale = SalesTransaction::create($attributes + [
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'customer_name' => 'Online Customer',
            'status' => 'confirmed',
            'sub_total' => $attributes['grand_total'],
            'total_amount' => $attributes['grand_total'],
            'shipping_fee_amount' => $attributes['shipping_fee_amount'] ?? 0,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $attributes['grand_total'],
            'line_total' => $attributes['grand_total'],
        ]);

        return $sale;
    }
}
