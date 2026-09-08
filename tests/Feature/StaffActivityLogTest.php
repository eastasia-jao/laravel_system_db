<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PendingSale;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StaffActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_staff_can_view_logs_but_sales_marketing_staff_cannot(): void
    {
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff']);
        $salesStaff = User::factory()->create(['role' => 'sales_marketing_staff']);

        $this->actingAs($inventoryStaff)
            ->get(route('staff-logs.index'))
            ->assertOk()
            ->assertSee('Staff Activity Logs');

        $this->actingAs($salesStaff)
            ->get(route('staff-logs.index'))
            ->assertForbidden();
    }

    public function test_product_import_and_export_create_itemized_logs(): void
    {
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);

        $csv = implode("\n", [
            'ID,Item ID,Name,Description,Barcode,Brand,Group,Department,Cost,Sales,Wholesale,Shopee,Lazada,Tiktok,Stock,Unit',
            '1,SKU-001,Brush,Paint brush,12345,Brand A,Art,Tools,10,20,18,21,22,23,8,PC',
        ]);

        $this->actingAs($staff)
            ->post(route('hub.products.import', $hub), [
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ])
            ->assertRedirect(route('hub.dashboard', $hub->id));

        $product = Product::where('item_id', 'SKU-001')->sole();
        $importLog = StaffActivityLog::where('action_type', 'product_import')->sole();
        $this->assertSame(1, $importLog->items()->count());
        $this->assertDatabaseHas('staff_activity_log_items', [
            'staff_activity_log_id' => $importLog->id,
            'product_id' => $product->id,
            'operation' => 'created',
            'stock_after' => 8,
        ]);

        $this->actingAs($staff)
            ->get(route('hub.products.export', ['hub' => $hub->id, 'product_ids' => [$product->id]]))
            ->assertOk();

        $exportLog = StaffActivityLog::where('action_type', 'product_export')->sole();
        $this->assertDatabaseHas('staff_activity_log_items', [
            'staff_activity_log_id' => $exportLog->id,
            'product_id' => $product->id,
            'operation' => 'exported',
            'stock_before' => 8,
            'stock_after' => 8,
        ]);

        $this->actingAs($staff)
            ->get(route('staff-logs.show', $importLog))
            ->assertOk()
            ->assertSee('SKU-001')
            ->assertSee('Brush');
    }

    public function test_inventory_verification_creates_a_stock_deduction_log(): void
    {
        $hub = StoreHub::create(['name' => 'Verification Hub', 'code' => 'VERIFY', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $product = Product::create([
            'item_id' => 'SKU-VERIFY',
            'name' => 'Canvas Pad',
            'sales_price' => 120,
            'stock' => 10,
            'status' => 'active',
            'store_hub_id' => $hub->id,
        ]);
        $pendingSale = PendingSale::create([
            'store_hub_id' => $hub->id,
            'sales_channel' => 'Online',
            'placed_order_date' => now(),
            'invoice_number' => 'ORDER-VERIFY',
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 3,
                'unit_price' => 120,
            ]],
            'status' => 'pending',
            'submitted_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->post(route('sales.confirmPending', $pendingSale))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $log = StaffActivityLog::where('action_type', 'inventory_verification')->sole();
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseHas('staff_activity_log_items', [
            'staff_activity_log_id' => $log->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'stock_before' => 10,
            'stock_after' => 7,
        ]);
    }
}
