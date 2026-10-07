<?php

namespace Tests\Feature;

use App\Models\{Product, ProductStockAllocation, SalesTransaction, StoreHub, User, InventoryTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TikTokReturnsTest extends TestCase
{
    use RefreshDatabase;

    private function order(): array
    {
        $hub = StoreHub::create(['name' => 'Returns', 'code' => 'returns', 'status' => 'active', 'is_head_office' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::create(['item_id' => 'RET-1', 'name' => 'Paint', 'stock' => 8, 'sales_price' => 100, 'store_hub_id' => $hub->id, 'status' => 'active']);
        ProductStockAllocation::create(['product_id' => $product->id, 'tiktok' => 8]);
        $sale = SalesTransaction::create(['store_hub_id' => $hub->id, 'channel_type' => 'tiktok', 'order_date' => '2026-09-08', 'order_number' => 'RETURN-1', 'customer_name' => 'Customer', 'sales_after_transaction_fee' => 180, 'refund_shipping_fee' => 10]);
        $item = $sale->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);
        return [$hub, $product, $sale, $item];
    }

    public function test_received_return_restocks_once_and_refund_remains_editable(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        $url = route('hub.report.tiktok.return.update', [$hub->id, $sale->id, $item->id]);
        $data = ['return_status' => 'received', 'returned_quantity' => 1, 'return_condition' => 'good', 'refund_status' => 'pending', 'customer_refund_amount' => 100];
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $receivedAt = $item->fresh()->returned_at->toDateTimeString();
        $this->assertEquals(9, $product->fresh()->stock);
        $this->assertEquals(9, $product->stockAllocation->fresh()->tiktok);
        $this->assertEquals(170, $sale->fresh()->netTikTokPayout());
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'return',
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
        ]);
        $this->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
            ->assertOk()
            ->assertSee('Return order '.$sale->order_number)
            ->assertSee($sale->customer_name);
        $data['refund_status'] = 'completed';
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $this->assertEquals(9, $product->fresh()->stock);
        $this->assertEquals(1, InventoryTransaction::count());
        $this->assertEquals($receivedAt, $item->fresh()->returned_at->toDateTimeString());
        $this->assertEquals(70, $sale->fresh()->netTikTokPayout());
        $data['returned_quantity'] = 2;
        $this->patch($url, $data)->assertSessionHasErrors('returned_quantity');
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))
            ->assertNotFound();
    }

    public function test_damaged_returns_restore_only_physical_stock_and_refund_only_restores_nothing(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        $url = route('hub.report.tiktok.return.update', [$hub->id, $sale->id, $item->id]);
        $this->patch($url, ['return_status' => 'refund_only', 'returned_quantity' => 0, 'refund_status' => 'completed', 'customer_refund_amount' => 200])->assertSessionHasNoErrors();
        $this->assertEquals(8, $product->fresh()->stock);
        $this->assertEquals(8, $product->stockAllocation->fresh()->tiktok);
        $this->assertEquals(0, InventoryTransaction::count());
        $this->assertEquals(-30, $sale->fresh()->netTikTokPayout());
        $this->patch($url, ['return_status' => 'received', 'return_condition' => 'damaged', 'returned_quantity' => 2, 'refund_status' => 'completed', 'customer_refund_amount' => 200])->assertSessionHasNoErrors();
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals(8, $product->stockAllocation->fresh()->tiktok);
        $this->assertDatabaseHas('inventory_transactions', ['condition' => 'damaged', 'quantity' => 2]);
        $this->patch(route('hub.report.tiktok.update', [$hub->id, $sale->id]), ['sales_after_transaction_fee' => -30, 'refund_shipping_fee' => 10, 'payout_includes_refunds' => 1])
            ->assertSessionHasErrors('sales_after_transaction_fee');
        $this->assertEquals(-30, $sale->fresh()->netTikTokPayout());
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))
            ->assertNotFound();
    }

    public function test_partial_return_keeps_tiktok_payout_and_delivery_editable(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        $this->patch(route('hub.report.tiktok.return.update', [$hub->id, $sale->id, $item->id]), [
            'return_status' => 'received',
            'return_condition' => 'good',
            'returned_quantity' => 1,
            'refund_status' => 'completed',
            'customer_refund_amount' => 100,
        ])->assertSessionHasNoErrors();

        $this->patch(route('hub.report.tiktok.update', [$hub->id, $sale->id]), [
            'sales_after_transaction_fee' => 75,
            'drop_off_date' => '2026-09-10',
            'courier' => 'J&T',
        ])->assertSessionHasNoErrors();

        $this->assertSame('75.00', $sale->fresh()->tiktok_recalculated_payout);
        $this->assertSame('J&T', $sale->fresh()->courier);
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))
            ->assertNotFound();
    }

    public function test_invalid_returns_are_rejected_and_missing_payout_is_not_estimated(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        $url = route('hub.report.tiktok.return.update', [$hub->id, $sale->id, $item->id]);
        foreach ([['returned_quantity' => 3], ['returned_quantity' => 0], ['customer_refund_amount' => 201], ['return_condition' => ''], ['return_status' => 'none']] as $invalid) {
            $this->patch($url, array_merge(['return_status' => 'received', 'return_condition' => 'good', 'returned_quantity' => 1, 'refund_status' => 'completed', 'customer_refund_amount' => 100], $invalid))->assertSessionHasErrors();
        }
        $this->assertEquals(8, $product->fresh()->stock);
        $sale->update(['sales_after_transaction_fee' => null]);
        $this->assertNull($sale->fresh()->netTikTokPayout());
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))
            ->assertNotFound();
    }

    public function test_return_items_show_tiktok_orders_even_before_sales_total_is_entered(): void
    {
        [$hub, $product, $sale] = $this->order();
        $route = route('inventory-transactions.return.sales');
        $base = ['hub_id' => $hub->id, 'channel' => 'tiktok'];

        $this->getJson($route.'?'.http_build_query($base))
            ->assertOk()
            ->assertJsonPath('customers.0', 'Customer');

        $sale->update(['sales_after_transaction_fee' => null]);

        $this->getJson($route.'?'.http_build_query($base))
            ->assertOk()
            ->assertJsonPath('customers.0', 'Customer');
        $this->getJson($route.'?'.http_build_query([...$base, 'transaction_id' => $sale->id]))
            ->assertOk()
            ->assertJsonPath('transaction.customer_name', 'Customer');

        $sale->update(['sales_after_transaction_fee' => 450]);

        $this->getJson($route.'?'.http_build_query([...$base, 'customer' => 'Customer']))
            ->assertOk()
            ->assertJsonPath('orders.0.id', $sale->id);
    }

    public function test_tiktok_sales_marketing_staff_cannot_open_the_removed_sales_report(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['tiktok'],
        ]);

        $this->actingAs($staff)
            ->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))
            ->assertNotFound();
    }

    public function test_tiktok_sales_marketing_staff_can_open_returns_from_the_hub_dashboard(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'tiktok',
            'quantity' => 1,
            'condition' => 'good',
            'occurred_on' => '2026-09-09',
            'notes' => 'Inventory checked and returned to stock.',
            'created_by' => auth()->id(),
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['tiktok'],
        ]);

        $this->actingAs($staff)
            ->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('TikTok Returns')
            ->assertSee(route('hub.tiktok-returns', $hub->id), false);

        $returnsPage = $this->get(route('hub.tiktok-returns', $hub->id));
        $returnsPage->assertOk()
            ->assertSee('TikTok Returns')
            ->assertSee('RETURN-1')
            ->assertSee('Customer')
            ->assertSee('TikTok Order #')
            ->assertSee('Customer name')
            ->assertSee('Return summary')
            ->assertDontSee('Notes')
            ->assertDontSee('Inventory checked and returned to stock.')
            ->assertSee('View return items')
            ->assertSee('data-bs-target="#return-items-modal-'.$sale->id.'"', false)
            ->assertSee('id="return-items-modal-'.$sale->id.'"', false)
            ->assertDontSee('class="collapse"', false)
            ->assertSee('Item status')
            ->assertSee('Good: 1')
            ->assertSee('Return quantity')
            ->assertSee('1</td>', false)
            ->assertSee('Return quantities and item conditions below are recorded by inventory staff.')
            ->assertDontSee('name="return_reason"', false)
            ->assertDontSee('What happened?')
            ->assertDontSee('Save returned item')
            ->assertDontSee('>Clear</a>', false)
            ->assertDontSee('Sales Report');

        $this->get(route('hub.tiktok-returns', ['hub' => $hub->id, 'date_from' => '2026-09-09']))
            ->assertOk()
            ->assertSee('No TikTok orders found');

        $unassignedStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['online'],
        ]);
        $this->actingAs($unassignedStaff)
            ->get(route('hub.tiktok-returns', $hub->id))
            ->assertForbidden();
    }

    public function test_legacy_tiktok_returns_show_customer_name_using_order_reference(): void
    {
        [$hub, $product, $sale] = $this->order();
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'channel' => 'tiktok',
            'quantity' => 1,
            'condition' => 'good',
            'occurred_on' => '2026-09-09',
            'created_by' => auth()->id(),
        ]);

        $this->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
            ->assertOk()
            ->assertSee('Return order '.$sale->order_number)
            ->assertSee($sale->customer_name);
    }

    public function test_large_order_keeps_items_and_editors_inside_the_order_window(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        for ($i = 1; $i < 25; $i++) {
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        }
        InventoryTransaction::create([
            'type' => 'return',
            'reference' => $sale->order_number,
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'tiktok',
            'quantity' => 1,
            'condition' => 'good',
            'occurred_on' => '2026-09-09',
            'created_by' => auth()->id(),
        ]);
        $returns = $this->get(route('hub.tiktok-returns', ['hub' => $hub->id]));
        $returns->assertOk()->assertSee('View return items');
        $document = new \DOMDocument;
        @$document->loadHTML($returns->getContent());
        $xpath = new \DOMXPath($document);
        $orderModalId = 'return-items-modal-'.$sale->id;
        $this->assertSame(1, $xpath->query('//div[@id="'.$orderModalId.'"]//tbody/tr')->length);
        $this->assertSame(0, $xpath->query('//div[@id="'.$orderModalId.'"]//form')->length);
    }

    public function test_marketplace_returns_show_only_orders_and_items_with_recorded_returns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Marketplace Returns', 'code' => 'MKT-RETURNS', 'status' => 'active', 'is_head_office' => true]);
        $this->actingAs($admin);

        foreach (['tiktok', 'shopee', 'lazada'] as $channel) {
            $returnedProduct = Product::create([
                'item_id' => strtoupper($channel).'-RETURNED',
                'name' => ucfirst($channel).' returned product',
                'stock' => 1,
                'sales_price' => 100,
                'store_hub_id' => $hub->id,
                'status' => 'active',
            ]);
            $unreturnedProduct = Product::create([
                'item_id' => strtoupper($channel).'-NOT-RETURNED',
                'name' => ucfirst($channel).' unreturned product',
                'stock' => 1,
                'sales_price' => 100,
                'store_hub_id' => $hub->id,
                'status' => 'active',
            ]);
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'order_date' => '2026-09-08',
                'order_number' => strtoupper($channel).'-WITH-RETURN',
                'customer_name' => 'Returned Customer',
            ]);
            $returnedItem = $sale->items()->create([
                'product_id' => $returnedProduct->id,
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
            ]);
            $sale->items()->create([
                'product_id' => $unreturnedProduct->id,
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
            ]);
            $unreturnedSale = SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'order_date' => '2026-09-08',
                'order_number' => strtoupper($channel).'-NO-RETURN',
                'customer_name' => 'No Return Customer',
            ]);
            $unreturnedSale->items()->create([
                'product_id' => $unreturnedProduct->id,
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
            ]);
            InventoryTransaction::create([
                'type' => 'return',
                'reference' => $sale->order_number,
                'store_hub_id' => $hub->id,
                'product_id' => $returnedProduct->id,
                'sales_transaction_id' => $sale->id,
                'transaction_item_id' => $returnedItem->id,
                'channel' => $channel,
                'quantity' => 1,
                'condition' => 'good',
                'occurred_on' => '2026-09-09',
                'created_by' => $admin->id,
            ]);

            $url = $channel === 'tiktok'
                ? route('hub.tiktok-returns', $hub->id)
                : route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => $channel]);
            $this->get($url)
                ->assertOk()
                ->assertSee(strtoupper($channel).'-WITH-RETURN')
                ->assertSee(ucfirst($channel).' returned product')
                ->assertDontSee(strtoupper($channel).'-NO-RETURN')
                ->assertDontSee(ucfirst($channel).' unreturned product');
        }
    }
}
