<?php

namespace Tests\Feature;

use App\Jobs\AssignCatalogToBranch;
use App\Jobs\ProcessProductFileRequest;
use App\Models\CatalogProduct;
use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_catalog_keeps_independent_branch_stock_prices_and_ids(): void
    {
        $office = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'is_head_office' => true, 'status' => 'active']);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $first = Product::create(['store_hub_id' => $office->id, 'item_id' => '001', 'name' => 'Brush', 'stock' => 200, 'sales_price' => 100]);
        $second = Product::create(['store_hub_id' => $branch->id, 'item_id' => '001', 'name' => 'Local label', 'stock' => 12, 'sales_price' => 120]);
        $this->assertSame($first->catalog_product_id, $second->catalog_product_id);
        $this->assertDatabaseCount('catalog_products', 1);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $office->id]);
        $this->actingAs($staff)->post(route('catalog.assign'), ['source_hub_id' => $office->id, 'hub_id' => $branch->id, 'catalog_ids' => [$first->catalog_product_id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(12, (int) $second->fresh()->stock);
        $this->assertSame('120.00', $second->fresh()->sales_price);
        $this->assertSame('Brush', $second->fresh()->name);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('staff_activity_logs', ['action_type' => 'catalog_assignment', 'store_hub_id' => $branch->id]);
        $this->assertDatabaseHas('staff_activity_log_items', ['product_id' => $second->id, 'operation' => 'skipped', 'stock_after' => 12]);
        $third = StoreHub::create(['name' => 'New', 'code' => 'NEW', 'status' => 'active']);
        $this->post(route('catalog.assign'), ['source_hub_id' => $office->id, 'hub_id' => $third->id, 'catalog_ids' => [$first->catalog_product_id]])->assertRedirect();
        $this->assertDatabaseHas('products', ['store_hub_id' => $third->id, 'stock' => 0, 'catalog_product_id' => $first->catalog_product_id]);
        $this->get(route('catalog.index', ['hub_id' => $office->id]))->assertOk()->assertSee('Brush')->assertDontSee('Catalog activity logs');
        $this->post(route('catalog.assign'), ['source_hub_id' => $office->id, 'hub_id' => $office->id, 'catalog_ids' => [$first->catalog_product_id]])->assertSessionHasErrors('hub_id');
        $this->actingAs(User::factory()->create(['role' => 'sales_associate', 'hub_id' => $branch->id]))->post(route('catalog.assign'), ['hub_id' => $third->id, 'catalog_ids' => [$first->catalog_product_id]])->assertForbidden();
    }

    public function test_shared_catalog_sorts_item_ids_numerically(): void
    {
        foreach (['10', '2', '1', '20', '3'] as $itemId) {
            CatalogProduct::create(['item_id' => $itemId, 'name' => "Product {$itemId}"]);
        }

        $office = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'is_head_office' => true, 'status' => 'active']);
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('catalog.index', ['hub_id' => $office->id]));

        $response->assertOk();
        $this->assertSame(['1', '2', '3', '10', '20'], $response->viewData('catalog')->getCollection()->pluck('item_id')->all());
    }

    public function test_product_list_sorts_item_ids_numerically_before_paginating(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true, 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '1', '2'] as $itemId) {
            Product::create([
                'store_hub_id' => $hub->id,
                'item_id' => $itemId,
                'name' => "Product {$itemId}",
                'stock' => 10,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('products.index', ['hub_id' => $hub->id]));

        $response->assertOk();
        $this->assertSame(
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
            $response->viewData('products')->getCollection()->pluck('item_id')->all()
        );
    }

    public function test_inventory_staff_store_filter_excludes_all_stores_and_defaults_to_assigned_head_office(): void
    {
        $office = StoreHub::create(['name' => 'Main Warehouse', 'code' => 'HO-STORE', 'is_head_office' => true, 'status' => 'active']);
        $branch = StoreHub::create(['name' => 'Branch Store', 'code' => 'BR-STORE', 'status' => 'active']);
        Product::create(['store_hub_id' => $office->id, 'item_id' => 'STORE-1', 'name' => 'Office Product', 'stock' => 10]);
        Product::create(['store_hub_id' => $branch->id, 'item_id' => 'STORE-2', 'name' => 'Branch Product', 'stock' => 10]);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $office->id]);

        $defaultStore = $this->actingAs($staff)->get(route('products.index'));
        $defaultStore->assertOk()
            ->assertSee('<option value="'.$office->id.'" selected>', false)
            ->assertSee('<option value="'.$branch->id.'"', false)
            ->assertDontSee('All stores')
            ->assertSee('Office Product')
            ->assertDontSee('Branch Product');

        $this->get(route('products.index', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Branch Product')
            ->assertDontSee('Office Product');

        $this->get(route('products.index', ['hub_id' => $office->id]))
            ->assertOk()
            ->assertSee('Office Product')
            ->assertDontSee('Branch Product');
    }

    public function test_shared_catalog_requires_head_office_context_and_only_lists_regular_branches(): void
    {
        $office = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true, 'status' => 'active']);
        $branch = StoreHub::create(['name' => 'Designated Branch', 'code' => 'DB', 'status' => 'active']);
        $otherBranch = StoreHub::create(['name' => 'Other Branch', 'code' => 'OB', 'status' => 'active']);

        $staff = User::factory()->create([
            'role' => 'inventory_staff',
            'hub_id' => $office->id,
        ]);
        $response = $this->actingAs($staff)->get(route('catalog.index', ['hub_id' => $office->id]));

        $response->assertOk()
            ->assertSee('Head Office shared catalog')
            ->assertSee('<option value="'.$branch->id.'"', false)
            ->assertSee('<option value="'.$otherBranch->id.'"', false)
            ->assertDontSee('<option value="'.$office->id.'"', false);

        $catalog = CatalogProduct::create(['item_id' => 'DB-1', 'name' => 'Designated product']);
        $this->post(route('catalog.assign'), ['source_hub_id' => $office->id, 'hub_id' => $branch->id, 'catalog_ids' => [$catalog->id]])
            ->assertRedirect();
        $this->post(route('catalog.assign'), ['source_hub_id' => $branch->id, 'hub_id' => $otherBranch->id, 'catalog_ids' => [$catalog->id]])
            ->assertSessionHasErrors('source_hub_id');
        $this->actingAs(User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $branch->id]))
            ->get(route('catalog.index', ['hub_id' => $office->id]))
            ->assertForbidden();
        $this->actingAs($staff)
            ->get(route('products.index', ['hub_id' => $office->id]))
            ->assertOk()
            ->assertSee(route('catalog.index', ['hub_id' => $office->id]));
        $this->get(route('products.index', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertDontSee('Shared Product Catalog');
    }

    public function test_approval_queues_once_and_worker_completes_without_crossing_branches(): void
    {
        Queue::fake();
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => '001', 'name' => 'Brush', 'stock' => 12]);
        $record = ProductFileRequest::create(['type' => 'export', 'store_hub_id' => $hub->id, 'submitted_by' => $associate->id, 'product_ids' => [$product->id]]);
        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('queued', $record->fresh()->processing_status);
        $this->assertSame('pending', $record->fresh()->status);
        Queue::assertPushed(ProcessProductFileRequest::class, 1);
        $this->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertStatus(409);
        $this->post(route('product-file-requests.review', $record), ['decision' => 'rejected', 'rejection_reason' => 'No'])->assertStatus(409);
        $job = new ProcessProductFileRequest($record->id, $staff->id, '127.0.0.1');
        $job->handle();
        $this->assertSame('approved', $record->fresh()->status);
        $this->assertSame('completed', $record->fresh()->processing_status);
        $job->handle();
        $this->assertDatabaseCount('staff_activity_logs', 1);
        $this->actingAs($staff)->get(route('product-file-requests.download-csv', $record))->assertOk()->assertSee('Brush');
    }

    public function test_failed_job_can_be_retried_without_a_partial_import(): void
    {
        Queue::fake();
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $record = ProductFileRequest::create(['type' => 'import', 'store_hub_id' => $hub->id, 'submitted_by' => $staff->id, 'csv' => 'invalid', 'processing_status' => 'queued']);
        $job = new ProcessProductFileRequest($record->id, $staff->id, '127.0.0.1');
        try {
            $job->handle();
            $this->fail('Invalid CSV should fail.');
        } catch (ValidationException $exception) {
            $job->failed($exception);
        }
        $this->assertSame('failed', $record->fresh()->processing_status);
        $this->assertDatabaseCount('products', 0);
        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('queued', $record->fresh()->processing_status);
    }

    public function test_hub_page_does_not_embed_whole_product_catalog(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        Product::create(['store_hub_id' => $hub->id, 'item_id' => '001', 'name' => 'Unloaded Product Name', 'stock' => 12, 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]))->get(route('hub.dashboard', $hub->id))->assertOk()->assertDontSee('Unloaded Product Name')->assertSee('product-suggestions.js');
        $this->get(route('hub.products.search.ajax', ['hubId' => $hub->id, 'q' => 'Unloaded', 'active_only' => 1]))->assertOk()->assertJsonCount(1);
    }

    public function test_database_queue_serializes_and_processes_approved_exports(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => '001', 'name' => 'Queued product']);
        $record = ProductFileRequest::create(['type' => 'export', 'store_hub_id' => $hub->id, 'submitted_by' => $staff->id, 'product_ids' => [$product->id]]);
        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('queued', $record->fresh()->processing_status);
        $queued = Queue::connection('inventory')->pop('product-files');
        $this->assertNotNull($queued);
        $queued->fire();
        $this->assertSame('approved', $record->fresh()->status);
        $this->assertStringContainsString('Queued product', $record->fresh()->csv);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_entire_catalog_assignment_is_idempotent_and_keeps_existing_stock(): void
    {
        $office = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'is_head_office' => true, 'status' => 'active']);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $office->id]);
        $existing = Product::create(['store_hub_id' => $branch->id, 'item_id' => 'EXISTING', 'name' => 'Existing', 'stock' => 23, 'sales_price' => 19]);
        for ($i = 0; $i < 120; $i++) {
            CatalogProduct::create(['item_id' => 'NEW-'.$i, 'name' => 'New '.$i]);
        }
        $this->actingAs($staff)->post(route('catalog.assign'), ['source_hub_id' => $office->id, 'hub_id' => $branch->id, 'all_products' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'All shared catalog products were added'));
        $this->assertNull(Queue::connection('inventory')->pop('product-files'));
        $this->assertDatabaseCount('products', 121);
        $this->assertEquals(23, $existing->fresh()->stock);
        $this->assertEquals('19.00', $existing->fresh()->sales_price);
        $this->assertEquals(0, Product::where('id', '<>', $existing->id)->sum('stock'));
        $log = \App\Models\StaffActivityLog::where('action_type', 'catalog_assignment')->sole();
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame(120, $log->details['created_count']);
        $this->assertSame(121, $log->items()->count());
        $this->get(route('staff-logs.index', ['action' => 'catalog_assignment']))->assertOk()->assertSee('Catalog Assignment');
        (new AssignCatalogToBranch($branch->id, $staff->id, $office->id))->handle();
        $this->assertDatabaseCount('products', 121);
    }
}
