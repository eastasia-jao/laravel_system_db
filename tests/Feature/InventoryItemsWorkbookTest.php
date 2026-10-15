<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
use App\Support\EventPulloutWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Phar;
use PharData;
use Tests\TestCase;

class InventoryItemsWorkbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_and_restock_forms_use_excel_only_bulk_item_controls(): void
    {
        [$headOffice, $branch] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin']);

        $routes = [
            route('inventory-transactions.transfer.create', ['hub_id' => $headOffice->id, 'direction' => 'ho_to_branch']),
            route('inventory-transactions.branch-transfer.create', ['hub_id' => $branch->id]),
            route('inventory-transactions.restock.create', ['hub_id' => $headOffice->id]),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('Import Items (Excel)')
                ->assertSee('Download Excel Worksheet')
                ->assertSee('Export Items (Excel)')
                ->assertSee('.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', false)
                ->assertDontSee('Download CSV Worksheet');
        }
    }

    public function test_inventory_workbook_download_contains_form_metadata_and_stock_columns(): void
    {
        [$headOffice, $branch] = $this->hubs();
        Product::create([
            'name' => 'Canvas Roll',
            'description' => 'Canvas Roll 12x12',
            'item_id' => '000123',
            'barcode' => '000456',
            'store_hub_id' => $headOffice->id,
            'stock' => 8,
            'damaged_stock' => 2,
            'status' => 'active',
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('inventory-transactions.items-workbook', [
                'hub_id' => $headOffice->id,
                'target_hub_id' => $branch->id,
                'type' => 'stock_transfer',
                'transfer_direction' => 'ho_to_branch',
                'reference' => 'TRF-1001',
                'occurred_on' => '2026-10-15',
                'notes' => 'Handle carefully',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbook = EventPulloutWorkbook::readInventoryItems($response->baseResponse->getFile()->getPathname());
        $archive = new PharData($response->baseResponse->getFile()->getPathname(), 0, null, Phar::ZIP);
        $worksheetXml = $archive['xl/worksheets/sheet1.xml']->getContent();
        unset($archive);
        $this->assertSame('TRF-1001', $workbook['reference']);
        $this->assertSame('2026-10-15', $workbook['occurred_on']);
        $this->assertSame('Handle carefully', $workbook['notes']);
        $this->assertSame('000123', $workbook['items'][0]['item_id']);
        $this->assertSame('000456', $workbook['items'][0]['barcode']);
        $this->assertSame('10', $workbook['items'][0]['physical_stock']);
        $this->assertSame('', $workbook['items'][0]['quantity']);
        $this->assertStringContainsString('<dimension ref="A1:E3"/>', $worksheetXml);
        $this->assertStringContainsString('<mergeCell ref="D1:E1"/>', $worksheetXml);
        $this->assertStringNotContainsString('Good Items', $worksheetXml);
        $this->assertStringNotContainsString('Damaged Items', $worksheetXml);
    }

    public function test_exported_inventory_workbook_can_be_imported_with_quantity_and_metadata(): void
    {
        [$headOffice] = $this->hubs();
        $product = Product::create([
            'name' => 'Canvas Panel',
            'item_id' => 'PANEL-7',
            'barcode' => 'BAR-7',
            'store_hub_id' => $headOffice->id,
            'stock' => 7,
            'damaged_stock' => 1,
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $export = $this->actingAs($admin)->postJson(route('inventory-transactions.items-workbook.export'), [
            'hub_id' => $headOffice->id,
            'type' => 'restock',
            'occurred_on' => '2026-10-15',
            'reference' => 'RST-1001',
            'notes' => 'Warehouse delivery',
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertOk();
        $path = $export->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'inventory-workbook-test-');
        copy($path, $copy);

        try {
            $this->actingAs($admin)->post(route('inventory-transactions.items-workbook.import'), [
                'hub_id' => $headOffice->id,
                'type' => 'restock',
                'file' => new UploadedFile(
                    $copy,
                    'restock.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])->assertOk()
                ->assertJsonPath('items.0.product_id', $product->id)
                ->assertJsonPath('items.0.quantity', 4)
                ->assertJsonPath('metadata.reference', 'RST-1001')
                ->assertJsonPath('metadata.occurred_on', '2026-10-15')
                ->assertJsonPath('metadata.notes', 'Warehouse delivery');
        } finally {
            if (file_exists($copy)) {
                unlink($copy);
            }
        }
    }

    private function hubs(): array
    {
        return [
            StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]),
            StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active', 'is_head_office' => false]),
        ];
    }
}
