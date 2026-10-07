<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\PendingSale;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlineWalkInReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_replacement_uses_allocated_stock_and_preserves_payment_status(): void
    {
        [$hub, $admin, $original, $replacement] = $this->fixtures('ONLINE');
        ProductStockAllocation::create(['product_id' => $original->id, 'online' => 0]);
        ProductStockAllocation::create(['product_id' => $replacement->id, 'online' => 5]);
        [$sale, $item] = $this->sale($admin, $hub, $original, 'online', 'not_applicable');
        $sale->update(['proof_amount' => 100]);

        $this->actingAs($admin)->getJson(route('hub.products.search.ajax', [
            'hubId' => $hub->id,
            'q' => 'ONLINE-BARCODE',
            'active_only' => 1,
            'stock_channel' => 'online',
        ]))->assertOk()->assertJsonPath('0.barcode', 'ONLINE-BARCODE');

        $this->actingAs($admin)->post(route('hub.report.online.replacement.store', [$hub, $sale, $item]), [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'replacement_shipping_fee_type' => 'Custom Amount',
            'replacement_shipping_fee_amount' => 15,
            'exchange_payment_amount' => 35,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Online customer requested another item.',
        ])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('product_replacements', 0);
        $this->get(route('hub.report', ['hub' => $hub, 'channel' => 'online']))
            ->assertOk()
            ->assertDontSee('data-replacement-target="#online-replacement-', false)
            ->assertSee('Unavailable');

        $original->increment('stock');
        $original->stockAllocation->increment('online');
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $original->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'online',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-09-23',
            'created_by' => $admin->id,
        ]);
        $this->get(route('hub.report', ['hub' => $hub, 'channel' => 'online']))
            ->assertOk()
            ->assertSee('online-replace-button', false)
            ->assertSee('Search by Item ID, barcode, or product name')
            ->assertSee('Exchange calculation')
            ->assertSee('name="exchange_payment_amount"', false);

        $this->actingAs($admin)->post(route('hub.report.online.replacement.store', [$hub, $sale, $item]), [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'replacement_shipping_fee_type' => 'Custom Amount',
            'replacement_shipping_fee_amount' => 15,
            'exchange_payment_amount' => 35,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Online customer requested another item.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $request = ProductReplacement::sole();
        $this->assertSame('pending', $request->status);
        $this->assertSame('120.00', $request->replacement_unit_price);

        $replacement->stockAllocation->update(['online' => 0]);
        $this->actingAs($admin)->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertSee('Not enough allocated Online stock for this item.')
            ->assertSee(route('stock-allocation.index', [
                'hub_id' => $hub->id,
                'search' => $replacement->item_id,
                'product_id' => $replacement->id,
                'return_to' => 'verification-queue',
                'return_hub_id' => $hub->id,
            ]))
            ->assertDontSee('One or more exchange products do not have enough allocated Wholesale stock.');

        $this->post(route('wholesale-replacements.approve', $request))
            ->assertSessionHasErrors('replacement');
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(5, $replacement->fresh()->stock);
        $replacement->stockAllocation->update(['online' => 5]);

        $this->post(route('wholesale-replacements.approve', $request))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame('135.00', $sale->fresh()->grand_total);
        $this->assertSame('15.00', $sale->fresh()->shipping_fee_amount);
        $this->assertSame('135.00', $sale->fresh()->proof_amount);
        $this->assertSame('not_applicable', $sale->fresh()->payment_status);
        $this->assertSame(4, $replacement->fresh()->stock);
        $this->assertSame(1, $original->stockAllocation->fresh()->online);
        $this->assertSame(4, $replacement->stockAllocation->fresh()->online);
        $this->assertSame(1, $original->fresh()->channelAvailableStock('online', false));
        $this->assertSame(4, $replacement->fresh()->channelAvailableStock('online', false));
        $this->assertDatabaseHas('inventory_transactions', ['channel' => 'online', 'type' => 'replacement_out']);

        $this->get(route('inventory-transactions.index', ['type' => 'replacement', 'hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Customer name')
            ->assertSee('Replacement Customer')
            ->assertSee('Order number')
            ->assertSee('ONLINE-ORDER');

        $this->get(route('hub.report', ['hub' => $hub, 'channel' => 'online']))
            ->assertOk()->assertSee('APPROVED')->assertSee('Online Replacement');
    }

    public function test_online_replacement_below_credit_can_be_submitted_without_changing_proof_amount(): void
    {
        [$hub, $admin, $original, $replacement] = $this->fixtures('ONLINE-LOWER');
        $replacement->update(['sales_price' => 80]);
        ProductStockAllocation::create(['product_id' => $original->id, 'online' => 0]);
        ProductStockAllocation::create(['product_id' => $replacement->id, 'online' => 5]);
        [$sale, $item] = $this->sale($admin, $hub, $original, 'online', 'not_applicable');
        $sale->update(['proof_amount' => 100]);
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $original->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'online',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-09-23',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('hub.report.online.replacement.store', [$hub, $sale, $item]), [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'replacement_shipping_fee_type' => 'Custom Amount',
            'replacement_shipping_fee_amount' => 5,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $request = ProductReplacement::sole();
        $this->assertSame('85.00', $request->exchange_total);
        $this->assertSame('0.00', $request->additional_payment_due);

        $original->increment('stock');
        $original->stockAllocation->increment('online');
        $this->post(route('wholesale-replacements.approve', $request))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('100.00', $sale->fresh()->proof_amount);
        $this->assertSame('5.00', $sale->fresh()->shipping_fee_amount);
        $this->assertSame('100.00', $sale->fresh()->grand_total);
        $this->assertDatabaseCount('sales_payment_records', 0);
    }

    public function test_online_replacement_quantity_cannot_exceed_received_return_quantity(): void
    {
        [$hub, $admin, $original, $replacement] = $this->fixtures('ONLINE-PARTIAL');
        ProductStockAllocation::create(['product_id' => $original->id, 'online' => 0]);
        ProductStockAllocation::create(['product_id' => $replacement->id, 'online' => 5]);
        [$sale, $item] = $this->sale($admin, $hub, $original, 'online', 'not_applicable');
        $item->update(['quantity' => 2, 'line_total' => 200]);

        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $original->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'online',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-09-23',
            'created_by' => $admin->id,
        ]);

        $payload = [
            'replacement_product_id' => $replacement->id,
            'replacement_quantity' => 1,
            'exchange_payment_amount' => 20,
            'exchange_payment_method' => 'CASH',
        ];
        $this->actingAs($admin)
            ->post(route('hub.report.online.replacement.store', [$hub, $sale, $item]), $payload + ['quantity' => 2])
            ->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('product_replacements', 0);

        $this->post(route('hub.report.online.replacement.store', [$hub, $sale, $item]), $payload + ['quantity' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_replacements', 1);
    }

    public function test_walk_in_replacement_uses_physical_stock_and_report_has_distinct_png_ui(): void
    {
        Storage::fake('public');
        [$hub, $admin, $original, $replacement] = $this->fixtures('WALK');
        [$sale, $item] = $this->sale($admin, $hub, $original, 'walk_in', 'paid');
        $hub->update(['is_head_office' => false]);
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);

        $report = $this->actingAs($associate)->get(route('hub.report', ['hub' => $hub, 'channel' => 'walk_in']));
        $report->assertOk()
            ->assertSee('Preview / Save PNG')
            ->assertSee('Walk-In Sales Report Preview')
            ->assertSee('Total gross sales')
            ->assertSee('Total Amount after discounts')
            ->assertSee('Total of Return')
            ->assertSee('Total Replacement')
            ->assertSee('View order')
            ->assertSee('Replace item')
            ->assertSee('walk-in-replacement-'.$item->id, false)
            ->assertSee('Exchange calculation')
            ->assertSee('Additional products')
            ->assertSee('data-walk-in-amount-due', false)
            ->assertSee('<option value="QRPH">QRPH</option>', false)
            ->assertDontSee('<option value="DATED_CHECK">', false)
            ->assertDontSee('<option value="POST_DATED_CHECK">', false)
            ->assertDontSee('<option value="COD">', false)
            ->assertSee('Return / refund');

        $this->post(route('hub.report.walk-in.replacement.store', [$hub, $sale, $item]), [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'exchange_payment_amount' => 20,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Walk-in customer exchanged the item.',
            'replacement_order_slip' => UploadedFile::fake()->create('replacement-order.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $requestNotification = $admin->notifications()->latest()->firstOrFail();
        $this->assertSame('Walk-In replacement verification needed', $requestNotification->data['title']);
        $this->assertSame('walk_in', $requestNotification->data['channel']);

        $request = ProductReplacement::sole();
        $this->assertNotEmpty($request->replacement_order_slip);
        Storage::disk('public')->assertExists($request->replacement_order_slip);
        $this->actingAs($admin)->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertSee('Open replacement order slip')
            ->assertSee('Inventory must record the original item as received');
        $this->assertSame(9, $original->fresh()->stock);
        $original->increment('stock');
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $original->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'walk_in',
            'condition' => 'good',
            'quantity' => 1,
            'occurred_on' => '2026-09-23',
            'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post(route('wholesale-replacements.approve', $request))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $approvalNotification = $associate->notifications()->latest()->firstOrFail();
        $this->assertSame('Walk-In replacement approved', $approvalNotification->data['title']);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(10, $original->fresh()->stock);
        $this->assertSame(4, $replacement->fresh()->stock);
        $this->assertSame(2, InventoryTransaction::where('channel', 'walk_in')->count());

        [$rejectedSale, $rejectedItem] = $this->sale($admin, $hub, $original, 'walk_in', 'paid');
        $rejectedSale->update(['order_number' => 'WALK-IN-REJECTED']);
        $this->post(route('hub.report.walk-in.replacement.store', [$hub, $rejectedSale, $rejectedItem]), [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'exchange_payment_amount' => 20,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Second exchange request.',
        ])->assertSessionHasNoErrors();
        $rejectedRequest = ProductReplacement::where('status', 'pending')->sole();
        $this->post(route('wholesale-replacements.reject', $rejectedRequest), [
            'rejection_reason' => 'Replacement item did not pass inspection.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('rejected', $rejectedRequest->fresh()->status);
        $this->assertSame(4, $replacement->fresh()->stock);

        $this->get(route('hub.report', ['hub' => $hub, 'channel' => 'walk_in']))
            ->assertOk()->assertSee('APPROVED')->assertSee('REJECTED')->assertSee('Walk-In Replacement')
            ->assertSee('walk-in-item-card', false)
            ->assertSee('Replacement history')
            ->assertSee('Total Returns (Qty) / Refunds')
            ->assertSee('View order slip')
            ->assertSee('Replacement item did not pass inspection.');
    }

    public function test_non_head_office_walk_in_replacement_rejects_unsupported_payment_methods(): void
    {
        [$hub, $admin, $original, $replacement] = $this->fixtures('WALK-MOP');
        [$sale, $item] = $this->sale($admin, $hub, $original, 'walk_in', 'paid');
        $hub->update(['is_head_office' => false]);

        $this->actingAs($admin)->post(route('hub.report.walk-in.replacement.store', [$hub, $sale, $item]), [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 1,
            'exchange_payment_amount' => 20,
            'exchange_payment_method' => 'DATED_CHECK',
            'exchange_bank_name' => 'BPI',
            'exchange_check_number' => '12345',
            'exchange_check_date' => '2026-09-29',
            'reason' => 'Unsupported walk-in payment method.',
        ])->assertSessionHasErrors('exchange_payment_method');

        $this->assertDatabaseCount('product_replacements', 0);
    }

    public function test_approved_replacements_restore_original_and_deduct_replacement_live_allocations_for_each_channel(): void
    {
        $hub = StoreHub::create(['name' => 'Channel Hub', 'code' => 'CHANNELS', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $routes = [
            'online' => 'hub.report.online.replacement.store',
            'tiktok' => 'hub.report.tiktok.replacement.store',
            'wholesale' => 'hub.report.wholesale.replace',
        ];

        foreach ($routes as $channel => $routeName) {
            $original = Product::create([
                'store_hub_id' => $hub->id, 'item_id' => strtoupper($channel).'-ORIGINAL',
                'name' => ucfirst($channel).' Original', 'stock' => 10, 'sales_price' => 100,
                'wholesale_price' => 100, 'status' => 'active',
            ]);
            $replacement = Product::create([
                'store_hub_id' => $hub->id, 'item_id' => strtoupper($channel).'-REPLACEMENT',
                'name' => ucfirst($channel).' Replacement', 'stock' => 10, 'sales_price' => 120,
                'wholesale_price' => 120, 'status' => 'active',
            ]);
            ProductStockAllocation::create(['product_id' => $original->id, $channel => 10]);
            ProductStockAllocation::create(['product_id' => $replacement->id, $channel => 10]);
            $pendingSale = PendingSale::create([
                'store_hub_id' => $hub->id,
                'submitted_by' => $admin->id,
                'sales_channel' => $channel,
                'placed_order_date' => '2026-09-23',
                'invoice_number' => strtoupper($channel).'-LIVE-BALANCE',
                'customer_name' => 'Allocation Customer',
                'items' => [['product_id' => $original->id, 'quantity' => 1, 'unit_price' => 100]],
                'sub_total' => 100,
                'total' => 100,
                'grand_total' => 100,
                'status' => 'pending',
                'payment_status' => $channel === 'wholesale' ? 'paid' : 'not_applicable',
            ]);

            $this->actingAs($admin)->post(route('sales.confirmPending', $pendingSale))
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertSame(9, $original->fresh()->stock);
            $this->assertSame(9, (int) $original->stockAllocation->fresh()->{$channel});
            $sale = SalesTransaction::where('order_number', strtoupper($channel).'-LIVE-BALANCE')->sole();
            $item = $sale->items()->sole();

            if ($channel === 'online') {
                $original->increment('stock');
                $original->stockAllocation->increment($channel);
                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $sale->order_number,
                    'store_hub_id' => $hub->id,
                    'product_id' => $original->id,
                    'sales_transaction_id' => $sale->id,
                    'transaction_item_id' => $item->id,
                    'channel' => $channel,
                    'condition' => 'good',
                    'quantity' => 1,
                    'occurred_on' => '2026-09-26',
                    'created_by' => $admin->id,
                ]);
            }

            $this->post(route($routeName, [$hub, $sale, $item]), [
                'replacement_product_id' => $replacement->id,
                'quantity' => 1,
                'replacement_quantity' => 1,
                'exchange_payment_amount' => 20,
                'exchange_payment_method' => 'CASH',
            ])->assertSessionHasNoErrors();
            $request = ProductReplacement::where('transaction_id', $sale->id)->sole();
            if ($channel === 'wholesale') {
                $replacement->stockAllocation->update(['wholesale' => 0]);
                $this->get(route('hub.sales.pending', $hub->id))
                    ->assertOk()
                    ->assertSee('Not enough allocated Wholesale stock for this item.')
                    ->assertSee(route('stock-allocation.index', [
                        'hub_id' => $hub->id,
                        'search' => $replacement->item_id,
                        'product_id' => $replacement->id,
                        'return_to' => 'verification-queue',
                        'return_hub_id' => $hub->id,
                    ]))
                    ->assertDontSee('One or more exchange products do not have enough allocated Wholesale stock.');
                $replacement->stockAllocation->update(['wholesale' => 10]);
            }
            if ($channel !== 'online') {
                $original->increment('stock');
                $original->stockAllocation->increment($channel);
                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $sale->order_number,
                    'store_hub_id' => $hub->id,
                    'product_id' => $original->id,
                    'sales_transaction_id' => $sale->id,
                    'transaction_item_id' => $item->id,
                    'channel' => $channel,
                    'condition' => 'good',
                    'quantity' => 1,
                    'occurred_on' => '2026-09-26',
                    'created_by' => $admin->id,
                ]);
            }
            $this->post(route('wholesale-replacements.approve', $request))->assertSessionHasNoErrors();

            $this->assertSame(10, (int) $original->stockAllocation->fresh()->{$channel});
            $this->assertSame(9, (int) $replacement->stockAllocation->fresh()->{$channel});
            $this->assertSame(10, $original->fresh()->channelAvailableStock($channel, false));
            $this->assertSame(9, $replacement->fresh()->channelAvailableStock($channel, false));

            $search = $this->getJson(route('hub.products.search.ajax', [
                'hubId' => $hub->id,
                'q' => $replacement->item_id,
                'active_only' => 1,
                'stock_channel' => $channel,
            ]))->assertOk()->json();
            $result = collect($search)->firstWhere('id', $replacement->id);
            $this->assertSame(9, (int) $result['channel_available_stock']);
        }

        $listed = $this->get(route('stock-allocation.index', ['hub_id' => $hub->id]))
            ->assertOk()->viewData('products')->getCollection()->keyBy('item_id');
        foreach (array_keys($routes) as $channel) {
            $this->assertSame(10, $listed[strtoupper($channel).'-ORIGINAL']->remaining_values[$channel]);
            $this->assertSame(9, $listed[strtoupper($channel).'-REPLACEMENT']->remaining_values[$channel]);
        }
    }

    private function fixtures(string $prefix): array
    {
        $hub = StoreHub::create(['name' => $prefix.' Hub', 'code' => $prefix, 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $original = Product::create(['store_hub_id' => $hub->id, 'item_id' => $prefix.'-ORIGINAL', 'name' => $prefix.' Original', 'stock' => 9, 'sales_price' => 100, 'status' => 'active']);
        $replacement = Product::create(['store_hub_id' => $hub->id, 'item_id' => $prefix.'-REPLACEMENT', 'name' => $prefix === 'ONLINE' ? 'Online Replacement' : 'Walk-In Replacement', 'barcode' => $prefix.'-BARCODE', 'stock' => 5, 'sales_price' => 120, 'status' => 'active']);

        return [$hub, $admin, $original, $replacement];
    }

    private function sale(User $admin, StoreHub $hub, Product $product, string $channel, string $paymentStatus): array
    {
        $sale = SalesTransaction::create([
            'user_id' => $admin->id, 'store_hub_id' => $hub->id, 'channel_type' => $channel,
            'customer_name' => 'Replacement Customer', 'order_number' => strtoupper($channel).'-ORDER', 'order_date' => '2026-09-22',
            'sub_total' => 100, 'total_amount' => 100, 'grand_total' => 100, 'status' => 'confirmed',
            'payment_status' => $paymentStatus, 'amount_paid' => $paymentStatus === 'paid' ? 100 : 0,
            'delivery_status' => $channel === 'online' ? 'pending' : 'not_applicable',
        ]);
        $item = $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);

        return [$sale, $item];
    }
}
