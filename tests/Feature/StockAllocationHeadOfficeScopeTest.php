<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAllocationHeadOfficeScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_stores_allocation_scope_only_lists_head_office_hubs_and_products(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-ALLOC', 'status' => 'active', 'is_head_office' => true]);
        $branch = StoreHub::create(['name' => 'Branch Store', 'code' => 'BR-ALLOC', 'status' => 'active', 'is_head_office' => false]);
        Product::create(['store_hub_id' => $headOffice->id, 'item_id' => 'HO-ITEM', 'name' => 'Head Office Item', 'stock' => 10, 'status' => 'active']);
        Product::create(['store_hub_id' => $branch->id, 'item_id' => 'BR-ITEM', 'name' => 'Branch Item', 'stock' => 10, 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('stock-allocation.index'));

        $response->assertOk()
            ->assertSee('HEAD OFFICE')
            ->assertSee('Head Office Item')
            ->assertDontSee('Branch Item')
            ->assertSee('name="hub_id" value="'.$headOffice->id.'"', false)
            ->assertViewHas('hub', fn ($hub) => $hub?->id === $headOffice->id);
        preg_match('/<select name="hub_id".*?<\/select>/s', $response->getContent(), $selector);
        $this->assertEmpty($selector);
        $this->assertSame([$headOffice->id], $response->viewData('allocationHubs')->modelKeys());
        $this->assertSame([$headOffice->id], $response->viewData('products')->getCollection()->pluck('store_hub_id')->unique()->all());
    }

    public function test_stock_allocation_shows_hub_selector_when_multiple_head_offices_exist(): void
    {
        StoreHub::create(['name' => 'First Head Office', 'code' => 'HO-FIRST', 'status' => 'active', 'is_head_office' => true]);
        StoreHub::create(['name' => 'Second Head Office', 'code' => 'HO-SECOND', 'status' => 'active', 'is_head_office' => true]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('stock-allocation.index'));

        $response->assertOk()
            ->assertSee('<select name="hub_id"', false)
            ->assertSee('FIRST HEAD OFFICE')
            ->assertSee('SECOND HEAD OFFICE')
            ->assertSee('<option value="">All stores</option>', false);
    }

    public function test_non_head_office_hub_cannot_be_opened_in_stock_allocations(): void
    {
        $branch = StoreHub::create(['name' => 'Branch Store', 'code' => 'BR-ALLOC', 'status' => 'active', 'is_head_office' => false]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('stock-allocation.index', ['hub_id' => $branch->id]))
            ->assertForbidden();
    }

    public function test_staff_assigned_to_non_head_office_branch_cannot_open_stock_allocations(): void
    {
        $branch = StoreHub::create(['name' => 'Branch Store', 'code' => 'BR-ALLOC', 'status' => 'active', 'is_head_office' => false]);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $branch->id]);

        $this->actingAs($staff)
            ->get(route('stock-allocation.index'))
            ->assertForbidden();
    }

    public function test_non_head_office_hub_cannot_be_updated_in_stock_allocations(): void
    {
        $branch = StoreHub::create(['name' => 'Branch Store', 'code' => 'BR-ALLOC', 'status' => 'active', 'is_head_office' => false]);
        $product = Product::create(['store_hub_id' => $branch->id, 'item_id' => 'BR-ITEM', 'name' => 'Branch Item', 'stock' => 10, 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('stock-allocation.update'), [
                'hub_id' => $branch->id,
                'allocations' => [
                    $product->id => ['online' => 0, 'wholesale' => 0, 'shopee' => 0, 'lazada' => 0, 'tiktok' => 0],
                ],
            ])
            ->assertForbidden();
    }

    public function test_head_office_can_filter_stock_allocations_to_items_sold_in_a_date_range(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-DATES', 'status' => 'active', 'is_head_office' => true]);
        $soldProduct = Product::create(['store_hub_id' => $headOffice->id, 'item_id' => 'SOLD-ITEM', 'name' => 'Sold During Range', 'stock' => 10, 'status' => 'active']);
        $outsideRangeProduct = Product::create(['store_hub_id' => $headOffice->id, 'item_id' => 'OLD-ITEM', 'name' => 'Sold Outside Range', 'stock' => 10, 'status' => 'active']);
        Product::create(['store_hub_id' => $headOffice->id, 'item_id' => 'NONE-ITEM', 'name' => 'Never Sold', 'stock' => 10, 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        $inRangeSale = SalesTransaction::create([
            'user_id' => $admin->id, 'store_hub_id' => $headOffice->id, 'channel_type' => 'shopee',
            'customer_name' => 'Range Customer', 'order_number' => 'RANGE-001', 'order_date' => '2026-10-02',
            'grand_total' => 300, 'status' => 'completed',
        ]);
        $inRangeSale->items()->create(['product_id' => $soldProduct->id, 'quantity' => 3, 'unit_price' => 100, 'line_total' => 300]);

        $outsideRangeSale = SalesTransaction::create([
            'user_id' => $admin->id, 'store_hub_id' => $headOffice->id, 'channel_type' => 'shopee',
            'customer_name' => 'Older Customer', 'order_number' => 'OLD-001', 'order_date' => '2026-09-30',
            'grand_total' => 100, 'status' => 'completed',
        ]);
        $outsideRangeSale->items()->create(['product_id' => $outsideRangeProduct->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);

        $response = $this->actingAs($admin)->get(route('stock-allocation.index', [
            'hub_id' => $headOffice->id,
            'sold_from' => '2026-10-02',
            'sold_to' => '2026-10-02',
        ]));

        $response->assertOk()
            ->assertSee('Sold During Range')
            ->assertDontSee('Sold Outside Range')
            ->assertDontSee('Never Sold')
            ->assertSee('2026-10-02 to 2026-10-02');
        $this->assertSame([$soldProduct->id], $response->viewData('products')->getCollection()->pluck('id')->all());
    }
}
