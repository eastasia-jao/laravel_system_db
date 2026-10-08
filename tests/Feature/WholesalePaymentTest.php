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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WholesalePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_submits_partial_payment_status_and_amount(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'channel_type' => 'wholesale',
            'customer_name' => 'Customer', 'order_number' => 'PARTIAL-1', 'order_date' => '2026-09-08',
            'grand_total' => 547.20, 'status' => 'confirmed', 'payment_status' => 'unpaid',
            'amount_paid' => 0, 'delivery_status' => 'pending',
        ]);
        $report = route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']);
        $html = $this->actingAs($user)->get($report)->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[contains(@class,"status-update-form")]//select[@name="payment_status" and not(@disabled)]')->length);
        $this->assertSame('547.2', $xpath->query('//form[contains(@class,"status-update-form")]//input[@name="amount_paid"]')->item(0)->getAttribute('max'));
        $this->assertStringContainsString('amountInput.value = Number(amountInput.dataset.orderTotal || 0).toFixed(2);', $html);
        $this->assertStringContainsString('data-wholesale-replacement-shipping-type', $html);
        $this->assertStringContainsString('Preview / Save PNG', $html);
        $this->assertStringNotContainsString('Print / Save PDF', $html);
        $this->from($report)->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'unpaid', 'amount_paid' => '10.00', 'delivery_status' => 'pending',
        ])->assertRedirect($report)->assertSessionHasErrors([
            'amount_paid' => 'An unpaid order cannot have an amount paid. Enter 0.00 or select Partial.',
        ]);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
        $this->assertSame('0.00', $sale->fresh()->amount_paid);
        $this->from($report)->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'partial', 'amount_paid' => '47.20', 'delivery_status' => 'pending',
            'withholding_tax_amount' => '5.47', 'wholesale_withholding_tax' => '541.73',
        ])->assertRedirect($report)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertSame('47.20', $sale->fresh()->amount_paid);
        $this->assertSame('5.47', $sale->fresh()->withholding_tax_amount);
        $this->assertSame('541.73', $sale->fresh()->wholesale_withholding_tax);
        $this->get($report)->assertOk()->assertSee('PARTIAL')->assertViewHas('totalSales', 47.20);

        $this->from($report)->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'paid', 'amount_paid' => '47.20', 'delivery_status' => 'pending',
        ])->assertRedirect($report)->assertSessionHasNoErrors()->assertSessionHas('success', 'Customer payment has been marked as fully paid.');
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('547.20', $sale->fresh()->amount_paid);
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
            ->assertSee('Collected from paid and partially paid orders; unpaid orders are excluded.')
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
            ->assertViewHas('stateCounts', ['all' => 2, 'open' => 1, 'completed' => 1]);

        $this->get(route('hub.report', $base + ['transaction_state' => 'open']))
            ->assertOk()->assertSee('OPEN-ORDER')->assertDontSee('COMPLETED-ORDER');
        $this->get(route('hub.report', $base + ['transaction_state' => 'completed']))
            ->assertOk()->assertSee('COMPLETED-ORDER')->assertDontSee('OPEN-ORDER');
    }

    public function test_wholesale_total_sales_shows_collected_paid_and_partial_amounts_only(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH-COLLECT', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['PAID-ORDER', 'paid', 100, 100],
            ['PARTIAL-ORDER', 'partial', 200, 50],
            ['UNPAID-ORDER', 'unpaid', 300, 25],
        ] as [$orderNumber, $paymentStatus, $orderTotal, $amountPaid]) {
            SalesTransaction::create([
                'user_id' => $user->id,
                'store_hub_id' => $hub->id,
                'channel_type' => 'wholesale',
                'customer_name' => 'Customer',
                'order_number' => $orderNumber,
                'order_date' => '2026-10-06',
                'grand_total' => $orderTotal,
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
            ->assertSee('Total Sales')
            ->assertSee('Collected from paid and partially paid orders; unpaid orders are excluded.')
            ->assertDontSee('Gross Sales')
            ->assertDontSee('>Reset</a>');
        $this->assertSame(150.0, $response->viewData('wholesaleCollectedSales'));

        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'online']))
            ->assertOk()
            ->assertDontSee('>Reset</a>');
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'walk_in']))
            ->assertOk()
            ->assertDontSee('>Reset</a>');
    }
}
