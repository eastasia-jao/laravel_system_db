<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductFileRequest;
use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HubQueuedImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(): string
    {
        return "ID,Item ID,Name,Description,Barcode,Brand,Retail Group,Retail Department,Cost Price,Sales Price,Wholesale Price,Shopee Price,Lazada Price,Tiktok Price,Stock,Unit Type\n1,SKU-1,Updated brush,Brush,001234,,,,1,2,2,2,2,2,12,pc\n";
    }

    public function test_hub_import_is_queued_then_exposes_its_status_and_results(): void
    {
        Queue::fake();
        $hub = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'SKU-1', 'name' => 'Original', 'stock' => 5]);
        $other = Product::create(['store_hub_id' => $branch->id, 'item_id' => 'SKU-1', 'name' => 'Other', 'stock' => 99]);
        $response = $this->actingAs($staff)->post(route('hub.products.import', $hub), ['file' => UploadedFile::fake()->createWithContent('products.csv', $this->csv())])->assertSessionHasNoErrors();
        $response->assertRedirect(route('hub.dashboard', $hub));
        $record = ProductFileRequest::sole();
        $this->assertSame('queued', $record->processing_status);
        $this->assertSame(1, $record->total_rows);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals(99, $other->fresh()->stock);
        Queue::assertPushed(ProcessProductFileRequest::class);
        $this->actingAs($staff)->getJson(route('hub.products.import-status', $hub))
            ->assertOk()->assertJsonPath('import.status', 'queued')->assertJsonPath('import.total_rows', 1);
        (new ProcessProductFileRequest($record->id, $staff->id, '127.0.0.1'))->handle();
        $this->assertEquals(12, $product->fresh()->stock);
        $this->assertSame('Updated brush', $product->fresh()->name);
        $this->assertSame('completed', $record->fresh()->processing_status);
        $this->actingAs($staff)->getJson(route('hub.products.import-status', $hub))
            ->assertOk()->assertJsonPath('import.status', 'completed')->assertJsonPath('import.updated_count', 1);
    }

    public function test_direct_branch_import_preserves_shared_metadata_and_other_branch_stock(): void
    {
        Queue::fake();
        $office = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $master = Product::create(['store_hub_id' => $office->id, 'item_id' => 'SKU-1', 'name' => 'Master brush', 'barcode' => '00999', 'stock' => 90]);
        $local = Product::create(['store_hub_id' => $branch->id, 'catalog_product_id' => $master->catalog_product_id, 'stock' => 4, 'sales_price' => 10]);
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $this->actingAs($staff)
            ->post(route('hub.products.import', $branch), ['file' => UploadedFile::fake()->createWithContent('products.csv', $this->csv())])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('hub.dashboard', $branch));
        $record = ProductFileRequest::sole();
        (new ProcessProductFileRequest($record->id, $staff->id, '127.0.0.1'))->handle();
        $this->assertSame('Master brush', $local->fresh()->name);
        $this->assertSame('00999', $local->fresh()->barcode);
        $this->assertEquals(12, $local->fresh()->stock);
        $this->assertSame('2.00', $local->fresh()->sales_price);
        $this->assertEquals(90, $master->fresh()->stock);
    }

    public function test_failed_direct_import_rolls_back_product_and_catalog_changes(): void
    {
        Queue::fake();
        $hub = StoreHub::create(['name' => 'Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $staff = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'SKU-1', 'name' => 'Original', 'stock' => 5]);
        $record = null;
        StaffActivityLog::creating(fn () => throw new \RuntimeException('Test audit failure'));
        try {
            $response = $this->actingAs($staff)
                ->post(route('hub.products.import', $hub), ['file' => UploadedFile::fake()->createWithContent('products.csv', $this->csv())]);
            $response->assertRedirect(route('hub.dashboard', $hub));
            $record = ProductFileRequest::sole();
            $job = new ProcessProductFileRequest($record->id, $staff->id, '127.0.0.1');
            try {
                $job->handle();
                $this->fail('The audit failure should stop the import job.');
            } catch (\RuntimeException $exception) {
                $job->failed($exception);
            }
        } finally {
            StaffActivityLog::flushEventListeners();
        }

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertSame('Original', $product->fresh()->name);
        $this->assertSame('Original', $product->fresh()->catalogProduct->name);
        $this->assertSame('failed', $record->fresh()->processing_status);
    }
}
