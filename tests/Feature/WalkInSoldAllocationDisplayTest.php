<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PendingSale;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkInSoldAllocationDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_sold_items_are_not_grouped_with_online_sales(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'WALK-IN-ITEM',
            'name' => 'Walk-In Item',
            'stock' => 10,
            'sales_price' => 100,
            'status' => 'active',
        ]);

        foreach (['online' => 2, 'walk_in' => 3] as $channel => $quantity) {
            $sale = SalesTransaction::create([
                'user_id' => $admin->id,
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'customer_name' => 'Test Customer',
                'order_number' => strtoupper($channel).'-ORDER',
                'order_date' => '2026-09-24',
                'status' => 'completed',
            ]);
            $sale->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => 100,
                'line_total' => 100 * $quantity,
            ]);
        }

        $listedProduct = $this->actingAs($admin)
            ->get(route('stock-allocation.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Walk-In')
            ->viewData('products')
            ->getCollection()
            ->firstWhere('id', $product->id);

        $this->assertSame(2, $listedProduct->sold_values->get('online'));
        $this->assertSame(3, $listedProduct->sold_values->get('walk_in'));
    }

    public function test_walk_in_sale_cannot_consume_stock_reserved_for_allocated_channels(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-RESERVED', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $product = Product::create([
            'store_hub_id' => $hub->id, 'item_id' => 'RESERVED-ITEM', 'name' => 'Reserved Item',
            'stock' => 50, 'sales_price' => 100, 'status' => 'active',
        ]);
        ProductStockAllocation::create([
            'product_id' => $product->id, 'online' => 10, 'wholesale' => 10,
            'shopee' => 10, 'lazada' => 10, 'tiktok' => 10,
        ]);
        $pending = PendingSale::create([
            'store_hub_id' => $hub->id, 'submitted_by' => $admin->id, 'sales_channel' => 'walk_in',
            'placed_order_date' => '2026-09-24', 'customer_name' => 'Walk-In Customer',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
            'sub_total' => 100, 'total' => 100, 'grand_total' => 100, 'status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $this->assertSame(0, $product->unallocatedStock());
        $this->actingAs($admin)->post(route('sales.confirmPending', $pending))
            ->assertSessionHas('error', 'Awaiting stock: Reserved Item (requested 1, available 0, short 1). No stock was deducted.');
        $this->assertSame(50, $product->fresh()->stock);
        $this->assertDatabaseCount('sales_transactions', 0);

        $this->get(route('sales.pending'))
            ->assertOk()
            ->assertSee('Stock Allocation')
            ->assertSee(route('stock-allocation.index', [
                'hub_id' => $hub->id,
                'search' => $product->item_id,
            ]));
    }
}
