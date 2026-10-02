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
        $data['refund_status'] = 'completed';
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $this->assertEquals(9, $product->fresh()->stock);
        $this->assertEquals(1, InventoryTransaction::count());
        $this->assertEquals($receivedAt, $item->fresh()->returned_at->toDateTimeString());
        $this->assertEquals(70, $sale->fresh()->netTikTokPayout());
        $data['returned_quantity'] = 2;
        $this->patch($url, $data)->assertSessionHasErrors('returned_quantity');
        $report = $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']));
        $report->assertOk()->assertSee('Edit payout')->assertSee('70.00');
        $this->assertEquals(70, $report->viewData('metrics')['net_platform_payout']);
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
        $report = $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))
            ->assertOk()
            ->assertSee('Edit payout &amp; delivery locked', false)
            ->assertDontSee('Enter the updated TikTok payout below');
        $this->assertEquals(0, $report->viewData('metrics')['net_platform_payout']);
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
        $report = $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']))->assertOk();
        $this->assertEquals(75, $report->viewData('metrics')['net_platform_payout']);
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
        $report = $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']));
        $report->assertOk()->assertSee('Awaiting sales total');
        $this->assertEquals(0, $report->viewData('metrics')['net_platform_payout']);
        $this->assertEquals(0, $report->viewData('metrics')['actual_platform_payout']);
    }

    public function test_return_items_hide_tiktok_orders_until_sales_total_is_entered(): void
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
            ->assertJsonCount(0, 'customers');
        $this->getJson($route.'?'.http_build_query([...$base, 'transaction_id' => $sale->id]))
            ->assertNotFound();

        $sale->update(['sales_after_transaction_fee' => 450]);

        $this->getJson($route.'?'.http_build_query([...$base, 'customer' => 'Customer']))
            ->assertOk()
            ->assertJsonPath('orders.0.id', $sale->id);
    }

    public function test_large_order_keeps_items_and_editors_inside_the_order_window(): void
    {
        [$hub, $product, $sale, $item] = $this->order();
        for ($i = 1; $i < 25; $i++) {
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        }
        $report = $this->withSession(['_old_input' => ['editing_order' => $sale->id, 'editing_item' => $item->id]])
            ->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']));
        $report->assertOk()->assertSee('25 products')->assertSee('View order');
        $document = new \DOMDocument;
        @$document->loadHTML($report->getContent());
        $xpath = new \DOMXPath($document);
        $orderModalId = 'tiktok-order-'.$sale->id;
        $this->assertSame(25, $xpath->query('//div[@id="tiktok-order-'.$sale->id.'"]//tr[@data-order-item]')->length);
        $this->assertSame(0, $xpath->query('//tr[@data-order-item and not(ancestor::div[contains(@class,"modal")])]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="'.$orderModalId.'"]//form[contains(@action, "/report/tiktok/")]')->length);
        $this->assertSame(0, $xpath->query('//form[contains(@action, "/report/tiktok/") and not(ancestor::div[@id="'.$orderModalId.'"])]')->length);
        $this->assertSame(1, $xpath->query('//div[@data-reopen-order]')->length);
    }
}
