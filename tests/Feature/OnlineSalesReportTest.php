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

        InventoryTransaction::create([
            'type' => 'sold',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'online',
            'quantity' => 2,
            'occurred_on' => '2026-10-21',
            'created_by' => $admin->id,
        ]);
        $this->get(route('inventory-transactions.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('data-refunded-order-log', false)
            ->assertSee('Refunded / Locked')
            ->assertSee('Refunded order '.$sale->order_number)
            ->assertSee('data-refund-lock-details', false)
            ->assertSee('The refund was completed and this order can no longer be edited or refunded again.');

        $legacyRefund = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'customer_name' => 'Legacy Refunded Customer',
            'order_number' => 'ONLINE-LEGACY-REFUND',
            'order_date' => '2026-10-21',
            'sub_total' => 500,
            'total_amount' => 500,
            'grand_total' => 500,
            'shipping_fee_amount' => 50,
            'proof_amount' => 550,
            'status' => 'confirmed',
            'payment_status' => 'refunded',
            'delivery_status' => 'delivered',
            'mode_of_payment' => 'GCASH',
        ]);
        $legacyRefund->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 500,
            'line_total' => 500,
        ]);

        $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'refunded_order',
            'status' => 'refunded',
        ]))
            ->assertOk()
            ->assertSee($sale->order_number)
            ->assertSee($legacyRefund->order_number)
            ->assertSee('Legacy Refunded Customer')
            ->assertDontSee('Sold order '.$sale->order_number);

        $refundedReport = $this->get($reportUrl)->assertOk()
            ->assertSee('Refunded and locked')
            ->assertViewHas('totalSales', 0.0)
            ->assertViewHas('totalTransactions', 0)
            ->assertViewHas('metrics', fn (array $metrics) => (float) $metrics['refund_total'] === 200.0);
        $this->assertSame(0, $refundedReport->viewData('customerMetrics')['total']);
        $this->assertSame(0, $refundedReport->viewData('onlinePaymentCounts')->sum());
        $this->assertTrue($refundedReport->viewData('monthlySales')->isEmpty());

        $dashboard = $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'channel' => 'online',
            'date' => '2026-10-21',
        ]))->assertOk();
        foreach (['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly'] as $period) {
            $this->assertSame(0.0, (float) $dashboard->viewData('salesTotals')[$period]);
        }
        $this->assertSame(0.0, (float) $dashboard->viewData('channelPaymentOverview')['total']);
        $this->assertSame(0, $dashboard->viewData('channelPaymentOverview')['transactions']);
        $this->patch(route('sales.status.update', $sale->id), [
            'delivery_status' => 'pending',
        ])->assertStatus(409);
        $this->from($reportUrl)->post($refundUrl)
            ->assertRedirect($reportUrl)
            ->assertSessionHasErrors('refund');
    }

    public function test_online_overview_separates_net_product_sales_from_shipping_and_reconciles_proofs(): void
    {
        $hub = StoreHub::create([
            'name' => 'Online Overview Hub',
            'code' => 'ONLINE-OVERVIEW',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $originalProduct = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'ONLINE-OVERVIEW-ORIGINAL',
            'name' => 'Online Overview Original',
            'stock' => 10,
            'sales_price' => 500,
            'status' => 'active',
        ]);
        $replacementProduct = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'ONLINE-OVERVIEW-REPLACEMENT',
            'name' => 'Online Overview Replacement',
            'stock' => 10,
            'sales_price' => 600,
            'status' => 'active',
        ]);

        $replacementSale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'customer_name' => 'Replacement Customer',
            'order_number' => 'ONLINE-OVERVIEW-REPLACED',
            'order_date' => '2026-10-29',
            'sub_total' => 600,
            'total_amount' => 600,
            'grand_total' => 700,
            'shipping_fee_amount' => 100,
            'proof_amount' => 700,
            'mode_of_payment' => 'GCASH',
            'status' => 'confirmed',
            'payment_status' => 'not_applicable',
            'delivery_status' => 'delivered',
        ]);
        $replacementItem = $replacementSale->items()->create([
            'product_id' => $originalProduct->id,
            'quantity' => 1,
            'returned_quantity' => 1,
            'unit_price' => 500,
            'line_total' => 500,
        ]);
        ProductReplacement::create([
            'transaction_id' => $replacementSale->id,
            'transaction_item_id' => $replacementItem->id,
            'original_product_id' => $originalProduct->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'original_unit_price' => 500,
            'replacement_unit_price' => 600,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);

        $returnedSale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'customer_name' => 'Return Customer',
            'order_number' => 'ONLINE-OVERVIEW-RETURNED',
            'order_date' => '2026-10-30',
            'sub_total' => 400,
            'total_amount' => 400,
            'grand_total' => 400,
            'shipping_fee_amount' => 50,
            'proof_amount' => 250,
            'mode_of_payment' => 'PAYMAYA',
            'status' => 'confirmed',
            'payment_status' => 'not_applicable',
            'delivery_status' => 'delivered',
        ]);
        $returnedItem = $returnedSale->items()->create([
            'product_id' => $originalProduct->id,
            'quantity' => 2,
            'unit_price' => 200,
            'line_total' => 400,
        ]);
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $returnedSale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $originalProduct->id,
            'sales_transaction_id' => $returnedSale->id,
            'transaction_item_id' => $returnedItem->id,
            'channel' => 'online',
            'condition' => 'good',
            'quantity' => 1,
            'refund_amount' => 200,
            'occurred_on' => '2026-10-30',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'online',
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]));

        $response->assertOk()
            ->assertSee('Net Product Sales')
            ->assertSee('online-report-summary-layout', false)
            ->assertSee('online-report-filters', false)
            ->assertSee('online-overview-panel', false)
            ->assertSee('online-overview-main', false)
            ->assertSee('online-overview-sales-highlight', false)
            ->assertSee('Payment totals')
            ->assertSee('Shipping Fees')
            ->assertSee('Proof Amount')
            ->assertDontSee('Expected Amount')
            ->assertDontSee('Payment Difference')
            ->assertViewHas('totalSales', 800.0)
            ->assertViewHas('metrics', fn (array $metrics) =>
                (float) $metrics['total_sales'] === 800.0
                && (float) $metrics['shipping_fees'] === 150.0
                && (float) $metrics['expected_amount'] === 950.0
                && (float) $metrics['proof_amount'] === 950.0
                && (float) $metrics['difference'] === 0.0
            );

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $layout = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' online-report-summary-layout ')]");
        $filters = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' online-report-filters ')]");
        $overview = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' online-overview-panel ')]");
        $onlineMetricsRow = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' report-metrics-row ')]");

        $this->assertSame(1, $layout->length);
        $this->assertSame($layout->item(0), $filters->item(0)->parentNode);
        $this->assertSame($layout->item(0), $overview->item(0)->parentNode);
        $this->assertSame(0, $onlineMetricsRow->length);

        $this->assertSame(600.0, (float) $response->viewData('onlinePaymentSales')->get('GCASH'));
        $this->assertSame(200.0, (float) $response->viewData('onlinePaymentSales')->get('PAYMAYA'));
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
