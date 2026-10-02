<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductFileRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.queue_connection' => 'sync']);
    }

    private function setupUsers(): array
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $staff = User::factory()->create(['role' => 'inventory_staff']);

        return [$hub, $associate, $staff];
    }

    private function csv(): string
    {
        return "ID,Item ID,Name,Barcode,Stock\n1,00001,Brush,001234,12\n";
    }

    private function createRequest(StoreHub $hub, User $associate, string $type, ?string $csv = null, ?array $productIds = null): ProductFileRequest
    {
        $record = ProductFileRequest::create([
            'submitted_by' => $associate->id,
            'store_hub_id' => $hub->id,
            'type' => $type,
            'status' => 'pending',
            'file_name' => $csv === null ? null : 'products.csv',
            'product_ids' => $productIds,
        ]);
        if ($csv !== null) {
            $record->storeCsv($csv);
        }

        return $record;
    }

    public function test_request_page_recovers_when_processing_columns_are_missing(): void
    {
        [, , $staff] = $this->setupUsers();
        Schema::table('product_file_requests', fn ($table) => $table->dropColumn(['processing_status', 'processing_error']));

        $migration = require base_path('database/migrations/2026_09_28_000001_repair_product_file_request_processing_columns.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('product_file_requests', 'processing_status'));
        $this->assertTrue(Schema::hasColumn('product_file_requests', 'processing_error'));
        $this->actingAs($staff)->get(route('product-file-requests.index'))->assertOk();
    }

    public function test_sales_associates_cannot_access_or_submit_product_file_requests(): void
    {
        [$hub, $associate] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'export', productIds: []);

        $this->actingAs($associate)
            ->get(route('products.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertDontSee('Product Import / Export Requests')
            ->assertDontSee('Import / Export Requests');
        $this->get(route('product-file-requests.index'))->assertForbidden();
        $this->post('/product-file-requests', ['type' => 'export', 'export_all' => 1])->assertStatus(405);
        $this->get(route('product-file-requests.show', $record))->assertForbidden();
        $this->get(route('product-file-requests.download', $record))->assertForbidden();
        $this->get(route('product-file-requests.download-csv', $record))->assertForbidden();
    }

    public function test_branch_import_and_export_are_visible_only_to_admin_and_inventory_staff(): void
    {
        $hub = StoreHub::create([
            'name' => 'Branch',
            'code' => 'BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'BR-001',
            'name' => 'Branch product',
            'stock' => 5,
        ]);

        foreach (['admin', 'inventory_staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('hub.dashboard', $hub))
                ->assertOk()
                ->assertSee('Import CSV')
                ->assertSee('Export CSV')
                ->assertSee('id="importProductModal"', false)
                ->assertSee('id="exportProductModal"', false);
        }

        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $this->actingAs($associate)
            ->get(route('hub.dashboard', $hub))
            ->assertOk()
            ->assertDontSee('Import CSV')
            ->assertDontSee('Export CSV')
            ->assertDontSee('id="importProductModal"', false)
            ->assertDontSee('id="exportProductModal"', false);
        $this->post(route('hub.products.import', $hub))->assertForbidden();
        $this->get(route('hub.products.export', ['hub' => $hub, 'export_all' => 1]))->assertForbidden();
    }

    public function test_csv_chunks_roll_back_with_the_request_and_legacy_csv_still_reads(): void
    {
        [$hub, $associate] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'import', $this->csv());
        $this->assertSame($this->csv(), $record->fresh()->csv);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                $record->storeCsv('replacement');
                throw new \RuntimeException('Simulated failure');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure', $exception->getMessage());
        }

        $this->assertSame($this->csv(), $record->fresh()->csv);
        $this->assertDatabaseCount('product_file_csv_chunks', 1);
    }

    public function test_import_waits_for_staff_approval_and_cannot_be_applied_twice(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'import', $this->csv());

        $this->assertDatabaseCount('products', 0);
        $this->assertSame('pending', $record->status);
        $this->actingAs($associate)->get(route('product-file-requests.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('product-file-requests.show', $record))->assertOk()->assertSee('Approve and apply import');
        $this->actingAs($associate)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertForbidden();
        $this->post(route('hub.products.import', $hub), ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $this->csv())])->assertForbidden();

        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('catalog_products', ['item_id' => '00001', 'barcode' => '001234']);
        $this->assertDatabaseHas('products', ['stock' => 12, 'store_hub_id' => $hub->id]);
        $this->assertSame('approved', $record->fresh()->status);
        $this->assertSame($staff->id, $record->fresh()->reviewed_by);
        $this->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertStatus(409);
        $this->assertDatabaseCount('staff_activity_logs', 1);
    }

    public function test_export_requires_approval_and_download_is_an_immutable_snapshot(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $product = Product::create(['name' => 'Brush', 'item_id' => '00001', 'barcode' => '001234', 'stock' => 12, 'store_hub_id' => $hub->id, 'status' => 'active']);
        $record = $this->createRequest($hub, $associate, 'export', productIds: [$product->id]);

        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
        $record->refresh();
        $this->assertMatchesRegularExpression('/^products_BR_\d{8}_\d{6}_\d+\.csv$/', $record->file_name);
        $product->update(['name' => 'Changed after approval']);
        $this->actingAs($associate)->get(route('product-file-requests.download-csv', $record))->assertForbidden();
        $this->actingAs($staff)->get(route('product-file-requests.download-csv', $record))
            ->assertOk()->assertSee('Brush')->assertDontSee('Changed after approval')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$record->file_name.'"');
        $this->get(route('product-file-requests.download', $record))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="'.pathinfo($record->file_name, PATHINFO_FILENAME).'.xlsx"');
    }

    public function test_rejection_requires_reason_and_leaves_stock_unchanged(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'import', $this->csv());

        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'rejected'])->assertSessionHasErrors('rejection_reason');
        $this->post(route('product-file-requests.review', $record), ['decision' => 'rejected', 'rejection_reason' => 'Wrong quantities'])->assertRedirect();
        $this->assertSame('rejected', $record->fresh()->status);
        $this->assertDatabaseCount('products', 0);
        $this->actingAs($associate)->get(route('product-file-requests.show', $record))->assertForbidden();
    }

    public function test_failed_import_approval_rolls_back_all_product_changes(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'import', $this->csv());
        StaffActivityLog::creating(function () {
            throw new \RuntimeException('Simulated audit failure');
        });

        try {
            $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertStatus(500);
            $this->assertDatabaseCount('products', 0);
            $this->assertSame('pending', $record->fresh()->status);
            $this->assertNull($record->fresh()->reviewed_by);
        } finally {
            StaffActivityLog::flushEventListeners();
        }
    }

    public function test_missing_export_product_cannot_be_approved_and_other_roles_cannot_access_requests(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'export', productIds: [999999]);

        $this->actingAs($staff)->post(route('product-file-requests.review', $record), ['decision' => 'approved'])->assertStatus(422);
        $this->assertSame('pending', $record->fresh()->status);
        $this->actingAs(User::factory()->create(['role' => 'sales_marketing_staff']))->get(route('product-file-requests.index'))->assertForbidden();
        $this->get(route('product-file-requests.show', $record))->assertForbidden();
    }

    public function test_request_list_is_hidden_while_individual_request_details_remain_available(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $record = $this->createRequest($hub, $associate, 'export', productIds: []);

        $this->actingAs($staff)->get(route('product-file-requests.index'))
            ->assertOk()
            ->assertSee('Stock Transfer Review Requests')
            ->assertDontSee('Product import and export requests')
            ->assertDontSee('View request')
            ->assertDontSee('#'.$record->id.' Export');

        $this->get(route('product-file-requests.show', $record))
            ->assertOk()
            ->assertSee('Export request #'.$record->id)
            ->assertSee('List of Products');
    }

    public function test_export_request_details_are_paginated_ten_products_at_a_time(): void
    {
        [$hub, $associate, $staff] = $this->setupUsers();
        $productIds = [];
        foreach (range(1, 11) as $number) {
            $productIds[] = Product::create([
                'name' => "Product {$number}",
                'item_id' => "PAGE-{$number}",
                'store_hub_id' => $hub->id,
            ])->id;
        }
        $record = $this->createRequest($hub, $associate, 'export', productIds: $productIds);

        $this->actingAs($staff)->get(route('product-file-requests.show', $record))
            ->assertOk()
            ->assertSee('PAGE-1')
            ->assertSee('PAGE-10')
            ->assertDontSee('PAGE-11');

        $this->get(route('product-file-requests.show', ['fileRequest' => $record, 'page' => 2]))
            ->assertOk()
            ->assertSee('PAGE-11')
            ->assertDontSee('PAGE-1</td>', false);
    }
}
