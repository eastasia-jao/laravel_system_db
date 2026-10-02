<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_branch_inventory_uses_shared_metadata_and_bounded_pages(): void
    {
        // Synthetic capacity check in the isolated test database, never the local inventory.
        for ($offset = 0; $offset < 1000; $offset += 100) {
            $catalog = [];
            for ($i = $offset + 1; $i <= $offset + 100; $i++) {
                $catalog[] = ['id' => $i, 'item_id' => 'CAP-'.$i, 'name' => 'Capacity item '.$i, 'barcode' => '000'.$i];
            }
            DB::table('catalog_products')->insert($catalog);
        }
        for ($branch = 1; $branch <= 100; $branch++) {
            DB::table('store_hubs')->insert(['id' => $branch, 'name' => 'Branch '.$branch, 'code' => 'CAP-'.$branch, 'status' => 'active']);
            for ($offset = 0; $offset < 1000; $offset += 250) {
                $rows = [];
                for ($item = $offset + 1; $item <= $offset + 250; $item++) {
                    $rows[] = ['catalog_product_id' => $item, 'store_hub_id' => $branch, 'stock' => 10, 'status' => 'active'];
                }
                DB::table('products')->insert($rows);
            }
        }
        $this->assertDatabaseCount('catalog_products', 1000);
        $this->assertDatabaseCount('products', 100000);
        $this->actingAs(User::factory()->create(['role' => 'sales_associate', 'hub_id' => 42]));
        $this->get(route('products.index'))->assertOk()->assertViewHas('products', fn ($page) => $page->count() === 10 && $page->total() === 1000 && $page->every(fn ($p) => $p->store_hub_id == 42));
        $this->get(route('hub.products.search.ajax', ['hubId' => 42, 'q' => 'Capacity']))->assertOk()->assertJsonCount(100);
        $this->get(route('hub.products.search.ajax', ['hubId' => 42, 'inventory_page' => 1]))->assertOk()->assertJsonCount(500);
        $this->get(route('hub.products.search.ajax', ['hubId' => 43, 'inventory_page' => 1]))->assertForbidden();
    }
}
