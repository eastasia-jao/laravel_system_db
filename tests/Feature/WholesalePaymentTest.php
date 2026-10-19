<?php

namespace Tests\Feature;

use App\Models\SalesTransaction;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\ProductStockAllocation;
use App\Models\InventoryTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WholesalePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_submits_partial_payment_status_and_amount(): void
    {
        Storage::fake('public');
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'channel_type' => 'wholesale',
            'customer_name' => 'Customer', 'order_number' => 'PARTIAL-1', 'order_date' => '2026-09-08',
            'grand_total' => 547.20, 'shipping_fee_amount' => 100, 'status' => 'confirmed', 'payment_status' => 'unpaid',
            'amount_paid' => 0, 'delivery_status' => 'pending',
        ]);
        $report = route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']);
        $html = $this->actingAs($user)->get($report)->assertOk()->getContent();
        $this->assertStringNotContainsString('toast-container', $html);
        $this->assertStringContainsString('window.AppAlert.show(message.text', $html);
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[contains(@class,"status-update-form")]//select[@name="payment_status" and not(@disabled)]')->length);
        $this->assertSame('547.2', $xpath->query('//form[contains(@class,"status-update-form")]//input[@name="amount_paid"]')->item(0)->getAttribute('max'));
        $this->assertSame('547.2', $xpath->query('//tr[@data-wholesale-order-row="'.$sale->id.'"]')->item(0)->getAttribute('data-balance'));
        $this->assertStringContainsString('amountInput.value = Number(amountInput.dataset.orderTotal || 0).toFixed(2);', $html);
        $this->assertStringContainsString('data-wholesale-replacement-shipping-type', $html);
        $this->assertStringContainsString('Preview / Save PNG', $html);
        $this->assertStringNotContainsString('Print / Save PDF', $html);
        $this->assertStringContainsString('id="wholesaleRefundConfirmModal"', $html);
        $this->assertStringContainsString('This cannot be undone.', $html);
        $this->assertStringNotContainsString('window.confirm(refundForm.dataset.confirmMessage', $html);
        $this->assertStringContainsString('wholesale-detail-main', $html);
        $this->assertStringContainsString('max-height: 340px; overflow: auto', $html);
        $this->assertStringContainsString('grid-template-columns: 1.6fr 1fr', $html);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $html);
        $this->assertStringContainsString('main.append(leftColumn, rightColumn)', $html);
        $this->assertStringContainsString('wholesale-payment-layout', $html);
        $this->assertStringContainsString('wholesale-status-actions', $html);
        $this->assertStringContainsString("createPanel('Update Status', 'fa-sliders', 'wholesale-detail-status')", $html);
        $this->assertStringContainsString('Upload payment proof', $html);
        $this->assertStringNotContainsString('Print Invoice', $html);
        $this->assertStringContainsString('wholesaleDetailsFooterActions', $html);
        $this->assertStringContainsString("footerActions?.prepend(refundForm)", $html);
        $this->assertStringContainsString('wholesale-save-button', $html);
        $this->from($report)->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'unpaid', 'amount_paid' => '10.00', 'delivery_status' => 'pending',
        ])->assertRedirect($report)->assertSessionHasErrors([
            'amount_paid' => 'An unpaid order cannot have an amount paid. Enter 0.00 or select Partial.',
        ]);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
        $this->assertSame('0.00', $sale->fresh()->amount_paid);
        $this->from($report)->followingRedirects()->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'partial', 'amount_paid' => '500.00', 'delivery_status' => 'pending',
            'withholding_tax_amount' => '5.47', 'wholesale_withholding_tax' => '12.34',
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])->assertOk()
            ->assertSessionHasNoErrors()
            ->assertSee('Wholesale order changes saved successfully.')
            ->assertSee('window.AppAlert.show(message.text', false)
            ->assertDontSee('toast-container');
        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertSame('500.00', $sale->fresh()->amount_paid);
        $this->assertSame('5.47', $sale->fresh()->withholding_tax_amount);
        $this->assertSame('12.34', $sale->fresh()->wholesale_withholding_tax);
        $this->assertNotNull($sale->fresh()->proof_of_payment);
        $this->assertStringEndsWith('.pdf', $sale->fresh()->proof_of_payment);
        Storage::disk('public')->assertExists($sale->fresh()->proof_of_payment);
        $this->assertSame($user->id, $sale->fresh()->last_updated_by);
        $this->get($report)->assertOk()->assertSee('PARTIAL')->assertViewHas('totalSales', 500.00);

        $this->from($report)->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'paid', 'amount_paid' => '47.20', 'delivery_status' => 'pending',
        ])->assertRedirect($report)->assertSessionHasNoErrors()->assertSessionHas('success', 'Wholesale order changes saved successfully.');
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('547.20', $sale->fresh()->amount_paid);
    }

    public function test_wholesale_refund_requires_all_items_returned_and_zeroes_sales_while_locking_order(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'store_hub_id' => $hub->id, 'item_id' => 'REFUND-1', 'name' => 'Refund Item',
            'stock' => 5, 'status' => 'active', 'wholesale_price' => 100,
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'wholesale',
            'customer_name' => 'Refund Customer',
            'order_number' => 'REFUND-ORDER-1',
            'order_date' => '2026-10-12',
            'sub_total' => 200,
            'total_amount' => 200,
            'grand_total' => 200,
            'amount_paid' => 150,
            'wholesale_withholding_tax' => 180,
            'status' => 'confirmed',
            'payment_status' => 'partial',
            'delivery_status' => 'delivered',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200,
        ]);
        $reportUrl = route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']);
        $refundUrl = route('hub.report.wholesale.refund', [$hub->id, $sale->id]);

        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'wholesale',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-10-13',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get($reportUrl)
            ->assertOk()
            ->assertSee('Refund is available after every ordered unit is recorded as returned.');
        $this->from($reportUrl)->post($refundUrl)->assertRedirect($reportUrl)
            ->assertSessionHasErrors('refund');
        $this->assertSame('confirmed', $sale->fresh()->status);
        $this->assertSame('200.00', $sale->fresh()->grand_total);

        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'wholesale',
            'condition' => 'damaged',
            'quantity' => 1,
            'occurred_on' => '2026-10-13',
            'created_by' => $user->id,
        ]);

        $replacementProduct = Product::create([
            'store_hub_id' => $hub->id, 'item_id' => 'REFUND-REPLACEMENT', 'name' => 'Replacement Item',
            'stock' => 3, 'status' => 'active', 'wholesale_price' => 100,
        ]);
        $replacement = ProductReplacement::create([
            'transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'original_product_id' => $product->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);
        $this->from($reportUrl)->post($refundUrl)->assertRedirect($reportUrl)
            ->assertSessionHasErrors('refund');
        $replacement->delete();

        $this->from($reportUrl)->post($refundUrl)
            ->assertRedirect($reportUrl)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $sale = $sale->fresh();
        $this->assertSame('refunded', $sale->status);
        $this->assertSame('refunded', $sale->payment_status);
        $this->assertSame('0.00', $sale->amount_paid);
        $this->assertSame('0.00', $sale->grand_total);
        $this->assertSame('0.00', $sale->total_amount);
        $this->assertSame('0.00', $sale->sub_total);
        $this->assertSame('200.00', $sale->refund_original_order_value);
        $this->assertSame('150.00', $sale->refund_original_collected);
        $this->assertSame('180.00', $sale->refund_original_withholding_tax);
        $this->assertSame('150.00', $sale->wholesale_refund_amount);
        $this->assertSame($user->id, $sale->refunded_by);
        $this->assertNotNull($sale->refunded_at);

        $this->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'wholesale',
            'transaction_state' => 'refunded',
        ]))->assertOk()
            ->assertSee('Refunded and locked')
            ->assertViewHas('totalSales', 0.0)
            ->assertViewHas('wholesaleCollectedSales', 0.0);

        $this->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'paid', 'amount_paid' => 200, 'delivery_status' => 'delivered',
        ])->assertStatus(409);
        $this->from($reportUrl)->post($refundUrl)->assertRedirect($reportUrl)->assertSessionHasErrors('refund');
        $this->assertSame('0.00', $sale->fresh()->grand_total);
    }

    public function test_wholesale_item_can_be_replaced_and_inventory_is_moved_once(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $original = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ORIG-1', 'name' => 'Original Item', 'stock' => 3, 'status' => 'active', 'wholesale_price' => 100]);
        $replacement = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'REPL-1', 'name' => 'Replacement Item', 'stock' => 5, 'status' => 'active', 'wholesale_price' => 120]);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'channel_type' => 'wholesale',
            'customer_name' => 'Customer', 'order_number' => 'REPLACE-1', 'order_date' => '2026-09-18',
            'sub_total' => 200, 'total_amount' => 200, 'grand_total' => 200, 'status' => 'confirmed', 'payment_status' => 'paid',
            'amount_paid' => 200, 'delivery_status' => 'delivered',
        ]);
        $item = $sale->items()->create(['product_id' => $original->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);

        $url = route('hub.report.wholesale.replace', [$hub->id, $sale->id, $item->id]);
        $reportUrl = route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']);
        $this->actingAs($user)->get($reportUrl)
            ->assertOk()
            ->assertSee('Available after inventory receives the returned item')
            ->assertSee('<button type="button" class="btn btn-outline-success btn-sm mt-2" disabled title="Available after inventory receives the returned item"', false);
        $this->actingAs($user)->from($reportUrl)->post($url, [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 2,
            'replacement_shipping_fee_type' => 'Custom Amount',
            'replacement_shipping_fee_amount' => 20,
            'exchange_payment_amount' => 160,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Customer requested a different item.',
        ])->assertRedirect($reportUrl)->assertSessionHasErrors('quantity');

        $original->increment('stock');
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $original->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'wholesale',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-09-19',
            'created_by' => $user->id,
        ]);
        $this->get($reportUrl)->assertOk()->assertSee('data-remaining="1"', false);

        $this->actingAs($user)->from($reportUrl)->post($url, [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 2,
            'replacement_shipping_fee_type' => 'Custom Amount',
            'replacement_shipping_fee_amount' => 20,
            'exchange_payment_amount' => 160,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Customer requested a different item.',
        ])->assertRedirect($reportUrl)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->get($reportUrl)
            ->assertOk()
            ->assertSee('Replacement request submitted', false)
            ->assertSee('No stock or order total has changed yet.', false);

        $this->assertSame(4, $original->fresh()->stock);
        $this->assertSame(5, $replacement->fresh()->stock);
        $this->assertSame(1, ProductReplacement::where('transaction_item_id', $item->id)->sum('quantity'));
        $request = ProductReplacement::where('transaction_item_id', $item->id)->sole();
        $this->assertSame('pending', $request->status);
        $this->assertSame(0, InventoryTransaction::whereIn('type', ['replacement_return', 'replacement_out'])->count());
        $this->assertSame('200.00', $sale->fresh()->grand_total);
        $this->actingAs($user)->get(route('sales.pending'))
            ->assertOk()->assertSee('EXCHANGE AWAITING VERIFICATION')->assertSee('Replacement Item');

        $this->actingAs($user)->post(route('wholesale-replacements.approve', $request->id))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(4, $original->fresh()->stock);
        $this->assertSame(3, $replacement->fresh()->stock);
        $this->assertSame(0, InventoryTransaction::where('type', 'replacement_return')->count());
        $this->assertSame(1, InventoryTransaction::where('type', 'replacement_out')->count());
        $this->assertSame('360.00', $sale->fresh()->grand_total);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('360.00', $sale->fresh()->amount_paid);

        ProductStockAllocation::create(['product_id' => $original->id, 'wholesale' => 2]);
        ProductStockAllocation::create(['product_id' => $replacement->id, 'wholesale' => 5]);
        $stockViewer = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['wholesale'],
        ]);
        $productPage = $this->actingAs($stockViewer)->get(route('products.index', [
            'hub_id' => $hub->id,
            'channel' => 'wholesale',
        ]));
        $productPage->assertOk()->assertSee('Wholesale');
        $listed = $productPage->viewData('products')->getCollection()->keyBy('id');
        $this->assertSame(1, $listed[$original->id]->allocated_available_stock);
        $this->assertSame(3, $listed[$replacement->id]->allocated_available_stock);

        $this->post($url, ['replacement_product_id' => $replacement->id, 'quantity' => 2, 'replacement_quantity' => 1])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(4, $original->fresh()->stock);
        $this->assertSame(3, $replacement->fresh()->stock);

        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']))
            ->assertOk()
            ->assertSee('Replacement Item')
            ->assertSee('Replaced/returned:')
            ->assertSee('APPROVED')
            ->assertSee('Wholesale activity overview')
            ->assertDontSee('Collected from paid and partially paid orders; unpaid orders are excluded.')
            ->assertDontSee('Gross Sales');
    }

    public function test_return_lookup_includes_paid_and_partial_wholesale_orders_but_excludes_unpaid_orders(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Returns', 'code' => 'WH-R', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $orders = collect([
            ['number' => 'WHOLESALE-UNPAID', 'payment_status' => 'unpaid', 'amount_paid' => 0],
            ['number' => 'WHOLESALE-PARTIAL', 'payment_status' => 'partial', 'amount_paid' => 50],
            ['number' => 'WHOLESALE-PAID', 'payment_status' => 'paid', 'amount_paid' => 100],
        ])->map(fn (array $order) => SalesTransaction::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'wholesale',
            'customer_name' => 'Wholesale Return Customer',
            'order_number' => $order['number'],
            'order_date' => '2026-10-08',
            'grand_total' => 100,
            'status' => 'confirmed',
            'payment_status' => $order['payment_status'],
            'amount_paid' => $order['amount_paid'],
        ]));

        $url = route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'wholesale',
            'customer' => 'Wholesale Return Customer',
        ]);
        $this->actingAs($user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('orders.0.order_number', 'WHOLESALE-PAID')
            ->assertJsonPath('orders.1.order_number', 'WHOLESALE-PARTIAL');

        $this->actingAs($user)->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'wholesale',
            'transaction_id' => $orders->first()->id,
        ]))->assertNotFound();
    }

    public function test_partial_wholesale_return_restores_stock_without_an_automatic_refund(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Returns', 'code' => 'WH-RF', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'PARTIAL-RETURN', 'name' => 'Partial return item', 'stock' => 2, 'status' => 'active', 'wholesale_price' => 100]);
        ProductStockAllocation::create(['product_id' => $product->id, 'wholesale' => 2]);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'channel_type' => 'wholesale',
            'customer_name' => 'Partial Customer', 'order_number' => 'PARTIAL-RETURN-1', 'order_date' => '2026-10-08',
            'sub_total' => 100, 'grand_total' => 100, 'status' => 'confirmed', 'payment_status' => 'partial', 'amount_paid' => 50,
        ]);
        $item = $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);

        $this->actingAs($user)->post(route('inventory-transactions.return.store'), [
            'type' => 'return', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-10-08', 'channel' => 'wholesale',
            'reference' => $sale->order_number, 'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id, 'transaction_item_id' => $item->id,
                'good_quantity' => 1, 'damaged_quantity' => 0, 'refund_amount' => 100,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_transactions', [
            'sales_transaction_id' => $sale->id, 'transaction_item_id' => $item->id, 'quantity' => 1, 'refund_amount' => 0,
        ]);
    }

    public function test_partial_wholesale_replacement_charges_only_the_revised_balance_including_shipping(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Exchange', 'code' => 'WH-EX', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $original = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ORIGINAL', 'name' => 'Original item', 'stock' => 3, 'status' => 'active', 'wholesale_price' => 100]);
        $replacement = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'REPLACEMENT', 'name' => 'Replacement item', 'stock' => 5, 'status' => 'active', 'wholesale_price' => 120]);
        ProductStockAllocation::create(['product_id' => $original->id, 'wholesale' => 2]);
        ProductStockAllocation::create(['product_id' => $replacement->id, 'wholesale' => 5]);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'channel_type' => 'wholesale',
            'customer_name' => 'Partial Exchange Customer', 'order_number' => 'PARTIAL-EXCHANGE-1', 'order_date' => '2026-10-08',
            'sub_total' => 200, 'total_amount' => 200, 'grand_total' => 200, 'status' => 'confirmed', 'payment_status' => 'partial', 'amount_paid' => 100,
        ]);
        $item = $sale->items()->create(['product_id' => $original->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);
        InventoryTransaction::create([
            'type' => 'return', 'store_hub_id' => $hub->id, 'product_id' => $original->id, 'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id, 'channel' => 'wholesale', 'condition' => 'good', 'quantity' => 1,
            'refund_amount' => 0, 'occurred_on' => '2026-10-08', 'created_by' => $user->id,
        ]);

        $url = route('hub.report.wholesale.replace', [$hub->id, $sale->id, $item->id]);
        $this->actingAs($user)->post($url, [
            'replacement_product_id' => $replacement->id, 'quantity' => 1, 'replacement_quantity' => 1,
            'replacement_shipping_fee_type' => 'Custom Amount', 'replacement_shipping_fee_amount' => 20,
            'exchange_payment_amount' => 140, 'exchange_payment_method' => 'CASH',
        ])->assertSessionHasNoErrors();

        $request = ProductReplacement::sole();
        $this->assertSame('0.00', $request->exchange_credit);
        $this->assertFalse($request->uses_exchange_credit);
        $this->assertSame('140.00', $request->additional_payment_due);

        $this->actingAs($user)->post(route('wholesale-replacements.approve', $request))->assertSessionHasNoErrors();
        $this->assertSame('240.00', $sale->fresh()->grand_total);
        $this->assertSame('240.00', $sale->fresh()->amount_paid);
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_wholesale_replacement_can_be_requested_after_inventory_recorded_the_return(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Returned Hub', 'code' => 'WH-RET', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $original = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'WH-RET-ORIG',
            'name' => 'Returned Wholesale Item',
            'stock' => 4,
            'status' => 'active',
            'wholesale_price' => 100,
        ]);
        $replacement = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'WH-RET-REPL',
            'name' => 'Replacement Wholesale Item',
            'stock' => 3,
            'status' => 'active',
            'wholesale_price' => 100,
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'wholesale',
            'customer_name' => 'Returned Customer',
            'order_number' => 'WH-RET-001',
            'order_date' => '2026-10-05',
            'sub_total' => 100,
            'grand_total' => 100,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        $item = $sale->items()->create([
            'product_id' => $original->id,
            'quantity' => 1,
            'returned_quantity' => 1,
            'return_status' => 'received',
            'return_condition' => 'good',
            'unit_price' => 100,
            'line_total' => 100,
        ]);
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $original->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'wholesale',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-10-06',
            'created_by' => $inventoryStaff->id,
        ]);

        $this->actingAs($admin)
            ->post(route('hub.report.wholesale.replace', [$hub->id, $sale->id, $item->id]), [
                'replacement_product_id' => $replacement->id,
                'quantity' => 1,
                'replacement_quantity' => 1,
                'exchange_payment_amount' => 0,
                'reason' => 'Replace the returned item.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $replacementRequest = ProductReplacement::where('transaction_item_id', $item->id)->sole();
        $this->assertSame('pending', $replacementRequest->status);
        $this->assertSame(4, (int) $original->fresh()->stock);
        $this->assertSame(3, (int) $replacement->fresh()->stock);

        ProductStockAllocation::create(['product_id' => $original->id, 'wholesale' => 2]);
        ProductStockAllocation::create(['product_id' => $replacement->id, 'wholesale' => 3]);
        $this->actingAs($inventoryStaff)
            ->post(route('wholesale-replacements.approve', $replacementRequest))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(4, $original->fresh()->stock);
        $this->assertSame(2, (int) $original->stockAllocation->fresh()->wholesale);
        $this->assertSame(2, $replacement->fresh()->stock);
        $this->assertSame(2, (int) $replacement->stockAllocation->fresh()->wholesale);
        $this->assertSame(1, InventoryTransaction::where('type', 'return')
            ->where('transaction_item_id', $item->id)
            ->count());
        $this->assertSame(0, InventoryTransaction::where('type', 'replacement_return')->count());
        $this->assertSame(1, InventoryTransaction::where('type', 'replacement_out')->count());
    }

    public function test_replacement_payment_proof_is_served_from_the_public_storage_disk(): void
    {
        Storage::fake('public');
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $original = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ATTACH-ORIG', 'name' => 'Original Item', 'stock' => 1, 'status' => 'active']);
        $replacementProduct = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ATTACH-REPL', 'name' => 'Replacement Item', 'stock' => 1, 'status' => 'active']);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'wholesale',
            'customer_name' => 'Customer',
            'order_number' => 'ATTACH-1',
            'order_date' => '2026-10-06',
            'grand_total' => 100,
            'status' => 'confirmed',
        ]);
        $item = $sale->items()->create(['product_id' => $original->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $path = 'exchange_payment_proofs/payment-proof.pdf';
        Storage::disk('public')->put($path, 'replacement payment proof');
        $replacement = ProductReplacement::create([
            'transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'original_product_id' => $original->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1,
            'exchange_payment_proofs' => [$path],
        ]);

        $this->actingAs($admin)
            ->get(route('hub.report.replacement-attachment', [
                'hub' => $hub->id,
                'replacement' => $replacement->id,
                'type' => 'payment-proof',
                'index' => 0,
            ]))
            ->assertOk()
            ->assertStreamedContent('replacement payment proof');
    }

    public function test_wholesale_report_filters_open_and_completed_transactions(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        foreach ([
            ['OPEN-ORDER', 'partial', 50, 'shipped'],
            ['COMPLETED-ORDER', 'paid', 100, 'delivered'],
        ] as [$number, $paymentStatus, $amountPaid, $deliveryStatus]) {
            SalesTransaction::create([
                'user_id' => $user->id,
                'store_hub_id' => $hub->id,
                'channel_type' => 'wholesale',
                'customer_name' => 'Customer',
                'order_number' => $number,
                'order_date' => '2026-09-18',
                'grand_total' => 100,
                'status' => 'confirmed',
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'delivery_status' => $deliveryStatus,
            ]);
        }

        $base = ['hub' => $hub->id, 'channel' => 'wholesale'];
        $all = $this->actingAs($user)->get(route('hub.report', $base));
        $all->assertOk()->assertSee('Open Transactions')->assertSee('Completed Transactions')
            ->assertSee('OPEN-ORDER')->assertSee('COMPLETED-ORDER')
            ->assertViewHas('stateCounts', ['all' => 2, 'open' => 1, 'completed' => 1, 'refunded' => 0]);

        $this->get(route('hub.report', $base + ['transaction_state' => 'open']))
            ->assertOk()->assertSee('OPEN-ORDER')->assertDontSee('COMPLETED-ORDER');
        $this->get(route('hub.report', $base + ['transaction_state' => 'completed']))
            ->assertOk()->assertSee('COMPLETED-ORDER')->assertDontSee('OPEN-ORDER');
    }

    public function test_wholesale_report_hides_total_sales_and_preserves_collected_values(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH-COLLECT', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['PAID-ORDER', 'paid', 100, 100, 20, null, null, null],
            ['PARTIAL-ORDER', 'partial', 200, 50, 30, null, null, null],
            ['UNPAID-ORDER', 'unpaid', 300, 0, 40, null, null, null],
            ['REPLACEMENT-ORDER', 'paid', 330, 330, 30, 300, null, null],
            ['WITHHOLDING-BOTH', 'unpaid', 1000, 0, 0, 1000, 25, 20],
            ['WITHHOLDING-AMOUNT', 'unpaid', 1000, 0, 0, 1000, null, 20],
            ['WITHHOLDING-TAX', 'unpaid', 1000, 0, 0, 1000, 20, null],
        ] as [$orderNumber, $paymentStatus, $orderTotal, $amountPaid, $shippingFee, $subTotal, $withholdingTax, $withholdingAmount]) {
            SalesTransaction::create([
                'user_id' => $user->id,
                'store_hub_id' => $hub->id,
                'channel_type' => 'wholesale',
                'customer_name' => 'Customer',
                'order_number' => $orderNumber,
                'order_date' => '2026-10-06',
                'grand_total' => $orderTotal,
                'sub_total' => $subTotal ?? $orderTotal,
                'shipping_fee_amount' => $shippingFee,
                'wholesale_withholding_tax' => $withholdingTax,
                'withholding_tax_amount' => $withholdingAmount,
                'amount_paid' => $amountPaid,
                'status' => 'confirmed',
                'payment_status' => $paymentStatus,
            ]);
        }

        $response = $this->actingAs($user)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'wholesale',
        ]));

        $response->assertOk()
            ->assertSee('Total Shipping Fee')
            ->assertDontSee('Collected from paid and partially paid orders; unpaid orders are excluded.')
            ->assertDontSee('Gross Sales')
            ->assertDontSee('>Reset</a>');
        $this->assertSame(450.0, $response->viewData('wholesaleCollectedSales'));
        $this->assertSame([
            'cash' => ['paid' => 400.0, 'partial' => 50.0],
            'withholding' => ['paid' => 0.0, 'partial' => 0.0],
        ], $response->viewData('wholesaleCollectionBreakdown'));
        $this->assertSame(120.0, $response->viewData('metrics')['shipping_fees']);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(6, $xpath->query('//section[@aria-label="Wholesale activity overview"]//div[contains(@class, "col-xl-4")]')->length);
        $paidRow = $xpath->query('//tr[@data-order-number="PAID-ORDER"]')->item(0);
        $partialRow = $xpath->query('//tr[@data-order-number="PARTIAL-ORDER"]')->item(0);
        $replacementRow = $xpath->query('//tr[@data-order-number="REPLACEMENT-ORDER"]')->item(0);
        $bothWithholdingRow = $xpath->query('//tr[@data-order-number="WITHHOLDING-BOTH"]')->item(0);
        $amountWithholdingRow = $xpath->query('//tr[@data-order-number="WITHHOLDING-AMOUNT"]')->item(0);
        $taxWithholdingRow = $xpath->query('//tr[@data-order-number="WITHHOLDING-TAX"]')->item(0);
        $this->assertEquals(100.0, (float) $paidRow->getAttribute('data-order-value'));
        $this->assertEquals(100.0, (float) $paidRow->getAttribute('data-collected'));
        $this->assertEquals(200.0, (float) $partialRow->getAttribute('data-order-value'));
        $this->assertEquals(50.0, (float) $partialRow->getAttribute('data-collected'));
        $this->assertEquals(330.0, (float) $replacementRow->getAttribute('data-order-value'));
        $this->assertEquals(330.0, (float) $replacementRow->getAttribute('data-collected'));
        $this->assertEquals(1000.0, (float) $bothWithholdingRow->getAttribute('data-order-value'));
        $this->assertEquals(975.0, (float) $bothWithholdingRow->getAttribute('data-balance'));
        $this->assertEquals(1000.0, (float) $bothWithholdingRow->getAttribute('data-subtotal'));
        $this->assertEquals(1000.0, (float) $amountWithholdingRow->getAttribute('data-order-value'));
        $this->assertEquals(980.0, (float) $amountWithholdingRow->getAttribute('data-balance'));
        $this->assertEquals(980.0, (float) $taxWithholdingRow->getAttribute('data-balance'));
        $this->assertSame('₱200.00', trim($xpath->query('./td[4]', $partialRow)->item(0)->textContent));
        $this->assertSame('₱50.00', trim($xpath->query('./td[5]', $partialRow)->item(0)->textContent));

        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'online']))
            ->assertOk()
            ->assertDontSee('>Reset</a>');
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'walk_in']))
            ->assertOk()
            ->assertDontSee('>Reset</a>');
    }

    public function test_wholesale_monthly_subtotal_prefers_withholding_tax_over_amount(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH-MONTHLY-TAX', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['ORDER-BOTH-1', 721.40, 300, 700],
            ['ORDER-BOTH-2', 344.30, 300, 44.30],
            ['ORDER-AMOUNT-ONLY', 344.30, null, 44.30],
        ] as [$orderNumber, $orderTotal, $withholdingTax, $withholdingAmount]) {
            SalesTransaction::create([
                'user_id' => $user->id,
                'store_hub_id' => $hub->id,
                'channel_type' => 'wholesale',
                'customer_name' => 'Customer',
                'order_number' => $orderNumber,
                'order_date' => '2026-10-06',
                'grand_total' => $orderTotal,
                'sub_total' => $orderTotal,
                'wholesale_withholding_tax' => $withholdingTax,
                'withholding_tax_amount' => $withholdingAmount,
                'amount_paid' => 0,
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
            ]);
        }

        $this->actingAs($user)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'wholesale',
        ]))->assertOk()->assertSee('October 2026 subtotal: ₱644.30');
    }

    public function test_wholesale_revenue_overview_counts_withholding_toward_settled_order_value(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH-SETTLED', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['PAID-CASH', 721.40, 721.40, null, null, 'paid', 'confirmed'],
            ['PAID-WITHHOLDING', 621.40, 321.40, 300, 44.30, 'paid', 'confirmed'],
            ['REFUNDED-ORDER', 344.30, 0, null, null, 'refunded', 'refunded'],
        ] as [$orderNumber, $orderTotal, $amountPaid, $withholdingTax, $withholdingAmount, $paymentStatus, $status]) {
            SalesTransaction::create([
                'user_id' => $user->id,
                'store_hub_id' => $hub->id,
                'channel_type' => 'wholesale',
                'customer_name' => 'Customer',
                'order_number' => $orderNumber,
                'order_date' => '2026-10-06',
                'grand_total' => $orderTotal,
                'sub_total' => $orderTotal,
                'amount_paid' => $amountPaid,
                'wholesale_withholding_tax' => $withholdingTax,
                'withholding_tax_amount' => $withholdingAmount,
                'status' => $status,
                'payment_status' => $paymentStatus,
            ]);
        }

        $response = $this->actingAs($user)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'wholesale',
        ]))->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $scorecard = $xpath->query('//*[@id="wholesaleReportImageSource"]')->item(0)->textContent;
        $refundedOrders = $xpath->query('//*[@id="wholesaleReportImageSource"]//div[contains(@class, "col-3")][.//div[normalize-space()="Refunded Orders"]]');

        $this->assertStringContainsString('100.0%', $scorecard);
        $this->assertStringContainsString('Subtotal', $scorecard);
        $this->assertStringContainsString('₱1,021.40', $scorecard);
        $this->assertStringContainsString('Withholding Tax', $scorecard);
        $this->assertStringContainsString('₱300.00', $scorecard);
        $this->assertStringContainsString('Withholding Tax Amount', $scorecard);
        $this->assertStringContainsString('₱44.30', $scorecard);
        $this->assertStringNotContainsString('Total Collected', $scorecard);
        $this->assertStringNotContainsString('Withholding (separate)', $scorecard);
        $this->assertStringContainsString('Order Value', $scorecard);
        $this->assertStringContainsString('₱1,342.80', $scorecard);
        $this->assertStringContainsString('₱0.00', $scorecard);
        $this->assertSame(1, $refundedOrders->length);
        $this->assertStringContainsString('1', $refundedOrders->item(0)->textContent);
    }
}
