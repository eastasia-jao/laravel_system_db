<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NormalizedInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_associate_search_ignores_other_branch_and_unassigned_accounts_see_no_products(): void
    {
        $hub = StoreHub::create(['name' => 'Assigned branch', 'code' => 'ASSIGNED', 'status' => 'active']);
        $other = StoreHub::create(['name' => 'Hidden branch', 'code' => 'HIDDEN', 'status' => 'active']);
        Product::create(['store_hub_id' => $hub->id, 'item_id' => 'BLUE-12', 'name' => 'Blue paint', 'brand' => 'Artist', 'barcode' => '001234']);
        Product::create(['store_hub_id' => $other->id, 'item_id' => 'PRIVATE', 'name' => 'Secret product']);
        $user = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $this->actingAs($user)->get(route('products.index', ['hub_id' => $other->id, 'search' => 'Artist blue']))
            ->assertOk()->assertSee('Blue paint')->assertDontSee('Secret product')->assertDontSee('Hidden branch')->assertDontSee('id="storeHubSelect"', false);
        $this->get(route('products.index', ['search' => '001234']))->assertOk()->assertSee('Blue paint');
        $user->update(['hub_id' => null]);
        $this->actingAs($user->fresh())->get(route('products.index'))->assertOk()->assertDontSee('Blue paint')->assertDontSee('Secret product')->assertSee('No branch assigned');
    }

    public function test_marketing_staff_can_choose_only_assigned_product_stock_channel_and_single_channel_hides_selector(): void
    {
        $hub = StoreHub::create(['name' => 'Marketing Branch', 'code' => 'MKT-BRANCH', 'status' => 'active']);
        Product::create(['store_hub_id' => $hub->id, 'item_id' => 'CHANNEL-1', 'name' => 'Channel stock product', 'stock' => 20]);

        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['shopee', 'lazada'],
        ]);
        $this->actingAs($staff)->get(route('products.index'))
            ->assertOk()
            ->assertSee('id="productChannel"', false)
            ->assertSee('Shopee')
            ->assertSee('Lazada')
            ->assertViewHas('displayChannel', 'shopee')
            ->assertViewHas('displayChannelLabel', 'Shopee');

        $this->get(route('products.index', ['channel' => 'lazada']))
            ->assertOk()
            ->assertSee('id="productChannel"', false)
            ->assertViewHas('displayChannel', 'lazada')
            ->assertViewHas('displayChannelLabel', 'Lazada');

        $this->get(route('products.index', ['channel' => 'tiktok']))->assertForbidden();

        $staff->update(['sales_channels' => ['lazada']]);
        $this->actingAs($staff->fresh())->get(route('products.index', ['channel' => 'shopee']))
            ->assertOk()
            ->assertDontSee('id="productChannel"', false)
            ->assertViewHas('displayChannel', 'lazada')
            ->assertViewHas('displayChannelLabel', 'Lazada');
    }

    public function test_marketplace_product_stock_never_increases_from_replacements_in_another_channel(): void
    {
        $hub = StoreHub::create(['name' => 'Stock Match Hub', 'code' => 'STOCK-MATCH', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['shopee', 'lazada'],
        ]);
        $product = Product::create([
            'store_hub_id' => $hub->id, 'item_id' => 'MKT-STOCK-1', 'name' => 'Marketplace stock product',
            'stock' => 20, 'status' => 'active',
        ]);
        $replacementProduct = Product::create([
            'store_hub_id' => $hub->id, 'item_id' => 'MKT-STOCK-2', 'name' => 'Replacement product',
            'stock' => 5, 'status' => 'active',
        ]);
        ProductStockAllocation::create([
            'product_id' => $product->id, 'shopee' => 10, 'lazada' => 10,
            'online' => 0, 'wholesale' => 0, 'tiktok' => 0,
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id, 'channel_type' => 'lazada', 'customer_name' => 'Lazada customer',
            'order_number' => 'LAZADA-MKT-STOCK', 'order_date' => '2026-09-28',
            'status' => 'completed', 'grand_total' => 100,
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);
        $replacement = ProductReplacement::create([
            'transaction_id' => $sale->id, 'transaction_item_id' => $item->id,
            'original_product_id' => $product->id, 'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1, 'replacement_quantity' => 1, 'status' => 'approved',
        ]);
        InventoryTransaction::create([
            'type' => 'replacement_return', 'store_hub_id' => $hub->id, 'product_id' => $product->id,
            'product_replacement_id' => $replacement->id, 'channel' => 'lazada', 'quantity' => 1,
            'occurred_on' => '2026-09-28',
        ]);

        $shopeePage = $this->actingAs($user)->get(route('products.index', ['channel' => 'shopee']));
        $shopeePage->assertOk()->assertViewHas('products', function ($page) use ($product) {
            return (int) $page->firstWhere('id', $product->id)->allocated_available_stock === 10;
        });
        $lazadaPage = $this->get(route('products.index', ['channel' => 'lazada']));
        $lazadaPage->assertOk()->assertViewHas('products', function ($page) use ($product) {
            return (int) $page->firstWhere('id', $product->id)->allocated_available_stock === 10;
        });
    }

    public function test_walk_in_product_stock_uses_head_office_unallocated_physical_stock(): void
    {
        $office = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HO-WALK-IN',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $office->id,
            'sales_channels' => ['walk_in'],
        ]);
        $product = Product::create([
            'store_hub_id' => $office->id,
            'item_id' => 'WALK-IN-PHYSICAL-1',
            'name' => 'Walk-In physical stock product',
            'stock' => 25,
        ]);
        ProductStockAllocation::create([
            'product_id' => $product->id,
            'online' => 5,
            'wholesale' => 4,
            'shopee' => 3,
            'lazada' => 2,
            'tiktok' => 1,
        ]);

        $this->actingAs($staff)
            ->get(route('products.index'))
            ->assertOk()
            ->assertViewHas('displayChannelLabel', 'Walk-In Physical Stock')
            ->assertViewHas('products', function ($products) use ($product) {
                return (int) $products->firstWhere('id', $product->id)->allocated_available_stock === 10;
            });
    }

    public function test_shared_fields_are_not_stored_on_branch_rows_and_updates_keep_stock_independent(): void
    {
        $office = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'status' => 'active']);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $first = Product::create(['item_id' => '0001', 'name' => 'Brush', 'barcode' => '00123', 'store_hub_id' => $office->id, 'stock' => 90, 'sales_price' => 10]);
        $second = Product::create(['item_id' => '0001', 'name' => 'Ignored local copy', 'store_hub_id' => $branch->id, 'stock' => 4, 'sales_price' => 15]);
        foreach (CatalogProduct::FIELDS as $field) {
            $this->assertFalse(Schema::hasColumn('products', $field));
        }
        $first->update(['name' => 'Updated master', 'barcode' => '00456', 'stock' => 89]);
        $this->assertSame('Updated master', $second->fresh()->name);
        $this->assertSame('00456', $second->fresh()->barcode);
        $this->assertEquals(4, $second->fresh()->stock);
        $this->assertSame('15.00', $second->fresh()->sales_price);
        $this->assertDatabaseCount('catalog_products', 1);
        $first->update(['item_id' => 'NEW-0001']);
        $this->assertSame('NEW-0001', $second->fresh()->item_id);
        $this->assertSame($first->id, $first->fresh()->id);
    }

    public function test_product_list_search_and_edit_use_shared_catalog_without_leaking_branches(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $other = StoreHub::create(['name' => 'Other', 'code' => 'OTHER', 'status' => 'active']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ABC', 'name' => 'Find me', 'barcode' => '00123', 'brand' => 'Brand A', 'status' => 'active']);
        Product::create(['store_hub_id' => $other->id, 'item_id' => 'PRIVATE', 'name' => 'Find me private', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]));
        foreach (['item_id' => 'ABC', 'name' => 'Find me', 'barcode' => '00123', 'brand' => 'Brand A'] as $field => $search) {
            $this->get(route('products.index', compact('field', 'search')))->assertOk()->assertSee('Find me')->assertDontSee('Find me private');
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('products.update', $product), ['item_id' => 'ABC', 'name' => 'Master edited', 'barcode' => '00999', 'sales_price' => 25])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Master edited', $product->fresh()->name);
    }
}
