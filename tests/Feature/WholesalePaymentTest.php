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
        ])->assertRedirect($report)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertSame('47.20', $sale->fresh()->amount_paid);
        $this->get($report)->assertOk()->assertSee('PARTIAL')->assertViewHas('totalSales', 47.20);
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
        $this->actingAs($user)->post($url, [
            'replacement_product_id' => $replacement->id,
            'quantity' => 1,
            'replacement_quantity' => 2,
            'exchange_payment_amount' => 140,
            'exchange_payment_method' => 'CASH',
            'reason' => 'Customer requested a different item.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(3, $original->fresh()->stock);
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
        $this->assertSame(1, InventoryTransaction::where('type', 'replacement_return')->count());
        $this->assertSame(1, InventoryTransaction::where('type', 'replacement_out')->count());
        $this->assertSame('340.00', $sale->fresh()->grand_total);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('340.00', $sale->fresh()->amount_paid);

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
            ->assertOk()->assertSee('Replacement Item')->assertSee('Replaced/returned:')->assertSee('APPROVED');
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
}
