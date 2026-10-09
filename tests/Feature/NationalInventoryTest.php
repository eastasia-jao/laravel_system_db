<?php

namespace Tests\Feature;

use App\Models\NationalProduct;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NationalInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_inventory_is_visible_only_for_head_office_admin_and_inventory_staff(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-NATIONAL', 'status' => 'active', 'is_head_office' => true]);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR-NATIONAL', 'status' => 'active', 'is_head_office' => false]);

        foreach (['admin', 'inventory_staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'hub_id' => $headOffice->id]);
            $this->actingAs($user)
                ->get(route('products.index', ['hub_id' => $headOffice->id]))
                ->assertOk()
                ->assertSee('National Inventory');
            $this->actingAs($user)
                ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
                ->assertOk()
                ->assertSee('Separate inventory:');
            $this->actingAs($user)
                ->get(route('products.index', ['hub_id' => $branch->id]))
                ->assertOk()
                ->assertDontSee('National Inventory');
            $this->actingAs($user)
                ->get(route('national-inventory.index', ['hub_id' => $branch->id]))
                ->assertForbidden();
        }

        $salesAssociate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $headOffice->id]);
        $this->actingAs($salesAssociate)
            ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertForbidden();

        $branchInventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $branch->id]);
        $this->actingAs($branchInventoryStaff)
            ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertForbidden();
    }

    public function test_national_csv_import_and_export_are_separate_from_store_products(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-NATIONAL', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $csv = implode("\n", [
            'ID,Item ID,Name,Description,Barcode,Brand,Stock,Unit Type',
            '1,="00001",National Brush,National-only brush,="007661234567891234567890",ArtCo,15,PCS',
            '2,NAT-2,National Paint,National-only paint,90002,ColorCo,9,CAN',
        ])."\n";

        $this->actingAs($admin)
            ->post(route('national-inventory.import'), [
                'hub_id' => $headOffice->id,
                'file' => UploadedFile::fake()->createWithContent('national.csv', $csv),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'National inventory import complete: 2 new and 0 updated item(s).')
            ->assertRedirect(route('national-inventory.index', ['hub_id' => $headOffice->id]));

        $this->assertDatabaseCount('national_products', 2);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame('007661234567891234567890', NationalProduct::where('item_id', '00001')->value('barcode'));
        $this->actingAs($admin)
            ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('nationalImportSuccessModal')
            ->assertSee('Import complete')
            ->assertSee('2 new and 0 updated item(s).');
        $importLog = StaffActivityLog::where('action_type', 'product_import')->sole();
        $this->assertSame('national', $importLog->details['inventory_scope']);
        $this->assertSame(2, $importLog->items()->count());

        $response = $this->actingAs($admin)->get(route('national-inventory.export', [
            'hub_id' => $headOffice->id,
            'export_all' => 1,
        ]))->assertOk();
        $this->assertStringContainsString('national_inventory_', $response->headers->get('Content-Disposition'));
        $export = $response->streamedContent();
        $this->assertStringContainsString('National Brush', $export);
        $lines = preg_split('/\r\n|\r|\n/', preg_replace('/^\xEF\xBB\xBF/', '', $export));
        $this->assertSame(['ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Brand', 'Stock', 'Unit Type'], str_getcsv($lines[0]));
        $firstProduct = str_getcsv($lines[1]);
        $this->assertSame('="00001"', $firstProduct[1]);
        $this->assertSame('="007661234567891234567890"', $firstProduct[4]);
        $this->assertFalse(Schema::hasColumn('national_products', 'retail_department'));
        $exportLog = StaffActivityLog::where('action_type', 'product_export')->sole();
        $this->assertSame(2, $exportLog->items()->count());

        $this->actingAs($admin)
            ->get(route('staff-logs.index'))
            ->assertOk()
            ->assertSee('National Product Import')
            ->assertSee('National Product Export');
        $this->actingAs($admin)
            ->get(route('staff-logs.show', $importLog))
            ->assertOk()
            ->assertSee('National Product Import Item List')
            ->assertSee('National Brush');
    }

    public function test_invalid_national_csv_is_rejected_before_any_writes(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-NATIONAL', 'status' => 'active', 'is_head_office' => true]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $headOffice->id]);
        $csv = "Item ID,Name,Stock\nNAT-1,Valid Item,5\nNAT-2,Bad Item,-3\n";

        $this->actingAs($inventoryStaff)
            ->post(route('national-inventory.import'), [
                'hub_id' => $headOffice->id,
                'file' => UploadedFile::fake()->createWithContent('national.csv', $csv),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('national_products', 0);
    }

    public function test_export_all_downloads_a_header_only_csv_when_national_inventory_is_empty(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-NATIONAL', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);

        $response = $this->actingAs($admin)->get(route('national-inventory.export', [
            'hub_id' => $headOffice->id,
            'export_all' => 1,
        ]))->assertOk();

        $this->assertStringContainsString('national_inventory_', $response->headers->get('Content-Disposition'));
        $export = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $export), fn ($line) => $line !== ''));
        $this->assertCount(1, $lines);
        $this->assertSame(['ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Brand', 'Stock', 'Unit Type'], str_getcsv($lines[0]));

        $exportLog = StaffActivityLog::where('action_type', 'product_export')->sole();
        $this->assertSame(0, $exportLog->details['item_count']);
        $this->assertSame(0, $exportLog->items()->count());

        $this->actingAs($admin)
            ->get(route('national-inventory.export', ['hub_id' => $headOffice->id, 'export_all' => 0]))
            ->assertSessionHasErrors('product_ids');
    }

    public function test_head_office_staff_can_edit_and_toggle_while_only_admin_can_delete_without_mutation_logs(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-NATIONAL', 'status' => 'active', 'is_head_office' => true]);
        $branch = StoreHub::create(['name' => 'Branch', 'code' => 'BR-NATIONAL', 'status' => 'active', 'is_head_office' => false]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $headOffice->id]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $branchStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $branch->id]);
        $product = NationalProduct::create([
            'item_id' => '00001',
            'name' => 'National Brush',
            'description' => 'Original description',
            'barcode' => '001234567890',
            'brand' => 'ArtCo',
            'stock' => 5,
            'unit_type' => 'PCS',
            'status' => 'active',
        ]);

        $this->actingAs($inventoryStaff)
            ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('Edit National product')
            ->assertSee('Deactivate National product')
            ->assertDontSee('Delete National product');

        $this->actingAs($inventoryStaff)
            ->put(route('national-inventory.update', $product), [
                'hub_id' => $headOffice->id,
                'item_id' => '00001',
                'name' => 'Updated National Brush',
                'description' => 'Updated description',
                'barcode' => '001234567890',
                'brand' => 'Updated ArtCo',
                'stock' => 12,
                'unit_type' => 'BOX',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('national-inventory.index', ['hub_id' => $headOffice->id]));
        $this->assertDatabaseHas('national_products', ['id' => $product->id, 'name' => 'Updated National Brush', 'stock' => 12, 'unit_type' => 'BOX']);

        $this->actingAs($inventoryStaff)
            ->patch(route('national-inventory.toggle', $product), ['hub_id' => $headOffice->id])
            ->assertSessionMissing('success')
            ->assertRedirect(route('national-inventory.index', ['hub_id' => $headOffice->id]));
        $this->assertSame('inactive', $product->fresh()->status);

        $this->actingAs($branchStaff)
            ->delete(route('national-inventory.destroy', $product), ['hub_id' => $headOffice->id])
            ->assertForbidden();
        $this->assertDatabaseHas('national_products', ['id' => $product->id]);

        $this->actingAs($inventoryStaff)
            ->delete(route('national-inventory.destroy', $product), ['hub_id' => $headOffice->id])
            ->assertForbidden();
        $this->assertDatabaseHas('national_products', ['id' => $product->id]);

        foreach (['product_update', 'product_status_change', 'product_delete'] as $actionType) {
            $this->assertDatabaseMissing('staff_activity_logs', ['action_type' => $actionType]);
        }

        $legacyMutationLog = StaffActivityLog::create([
            'user_id' => $inventoryStaff->id,
            'store_hub_id' => $headOffice->id,
            'action_type' => 'product_update',
            'description' => 'Legacy National product update that should stay hidden.',
            'details' => ['inventory_scope' => 'national'],
        ]);
        $this->actingAs($inventoryStaff)
            ->get(route('staff-logs.index'))
            ->assertOk()
            ->assertDontSee('Legacy National product update that should stay hidden.')
            ->assertDontSee('National Product Update')
            ->assertDontSee('National Status Change')
            ->assertDontSee('National Product Delete');
        $this->actingAs($inventoryStaff)
            ->get(route('staff-logs.show', $legacyMutationLog))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('Delete National product');
        $this->actingAs($admin)
            ->delete(route('national-inventory.destroy', $product), ['hub_id' => $headOffice->id])
            ->assertRedirect(route('national-inventory.index', ['hub_id' => $headOffice->id]));
        $this->assertDatabaseMissing('national_products', ['id' => $product->id]);
    }

    public function test_national_products_use_numeric_item_id_order_and_selection_mode_controls(): void
    {
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-NATIONAL', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        foreach (['10', '2', '1', '100', '11', '3'] as $itemId) {
            NationalProduct::create(['item_id' => $itemId, 'name' => "National Item {$itemId}", 'stock' => 0, 'status' => 'active']);
        }

        $this->actingAs($admin)
            ->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertViewHas('nationalProducts', fn ($products) => $products->pluck('item_id')->values()->all() === ['1', '2', '3', '10', '11', '100'])
            ->assertSee('Select Rows Mode')
            ->assertSee('Exit Selection Mode')
            ->assertSee('nationalExportSelected', false)
            ->assertSee('nationalImportProgress', false)
            ->assertDontSee('>Clear</a>', false);
    }
}
