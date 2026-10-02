<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TikTokReplacementStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_returns_available_tiktok_allocation_for_replacements(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'TIKTOK-STOCK',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'TT-REPLACEMENT',
            'name' => 'TikTok Replacement',
            'stock' => 50,
            'sales_price' => 450,
            'status' => 'active',
        ]);
        ProductStockAllocation::create([
            'product_id' => $product->id,
            'tiktok' => 6,
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'tiktok',
            'customer_name' => 'TikTok Customer',
            'order_number' => 'TT-STOCK-ORDER',
            'order_date' => '2026-09-22',
            'grand_total' => 900,
            'status' => 'confirmed',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 450,
            'line_total' => 900,
        ]);

        $this->actingAs($admin)
            ->getJson(route('hub.products.search.ajax', [
                'hubId' => $hub->id,
                'q' => 'TT-REPLACEMENT',
                'active_only' => 1,
                'stock_channel' => 'tiktok',
            ]))
            ->assertOk()
            ->assertJsonPath('0.id', $product->id)
            ->assertJsonPath('0.stock', 50)
            ->assertJsonPath('0.channel_available_stock', 6);
    }
}
