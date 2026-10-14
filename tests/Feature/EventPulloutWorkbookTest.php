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

class EventPulloutWorkbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_worksheet_contains_metadata_and_separate_stock_columns(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'EVENT-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        Product::create([
            'name' => 'Event Product',
            'description' => 'Event Product Description',
            'item_id' => '0000123',
            'barcode' => '0000456',
            'store_hub_id' => $hub->id,
            'stock' => 12,
            'damaged_stock' => 3,
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)
            ->get(route('inventory-transactions.sponsor.worksheet', [
                'hub_id' => $hub->id,
                'reference' => 'SW-EVENT-HO-20261001-120000-ABC123',
                'occurred_on' => '2026-10-01',
                'source' => 'Book Fair',
                'notes' => 'Handle with care',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbookPath = $response->baseResponse->getFile()->getPathname();
        $workbook = EventPulloutWorkbook::read($workbookPath);
        $archive = new PharData($workbookPath, 0, null, Phar::ZIP);
        $worksheetXml = $archive['xl/worksheets/sheet1.xml']->getContent();
        unset($archive);
        $this->assertStringContainsString('<mergeCell ref="D1:E1"/>', $worksheetXml);
        $this->assertStringContainsString('<mergeCell ref="F1:G1"/>', $worksheetXml);
        $this->assertSame('SW-EVENT-HO-20261001-120000-ABC123', $workbook['reference']);
        $this->assertSame('2026-10-01', $workbook['occurred_on']);
        $this->assertSame('Book Fair', $workbook['source']);
        $this->assertSame('Handle with care', $workbook['notes']);
        $this->assertSame([[
            'name' => 'Event Product Description',
            'barcode' => '0000456',
            'item_id' => '0000123',
            'physical_stock' => '15',
            'good_stock' => '12',
            'damaged_stock' => '3',
            'actual_pullout' => '',
        ]], $workbook['items']);
        $this->get(route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Import Items (Excel / CSV)')
            ->assertSee('Download Excel Worksheet')
            ->assertDontSee('Download CSV Worksheet');
    }

    public function test_selected_event_export_marks_good_and_damaged_rows_for_reimport(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'EVENT-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $product = Product::create([
            'name' => 'Event Product',
            'item_id' => 'EVENT-123',
            'store_hub_id' => $hub->id,
            'stock' => 12,
            'damaged_stock' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('inventory-transactions.sponsor.worksheet.export'), [
                'hub_id' => $hub->id,
                'occurred_on' => '2026-10-01',
                'source' => 'Book Fair',
                'reference' => 'SW-EVENT-123',
                'notes' => 'Selected items',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'stock_condition' => 'good'],
                    ['product_id' => $product->id, 'quantity' => 1, 'stock_condition' => 'damaged'],
                ],
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbook = EventPulloutWorkbook::read($response->baseResponse->getFile()->getPathname());
        $this->assertSame([
            ['name' => 'Event Product (GOOD)', 'quantity' => '2'],
            ['name' => 'Event Product (DAMAGED)', 'quantity' => '1'],
        ], array_map(
            fn ($item) => ['name' => $item['name'], 'quantity' => $item['actual_pullout']],
            $workbook['items']
        ));
    }

    public function test_event_workbook_import_restores_the_row_stock_condition_and_metadata(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'EVENT-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $product = Product::create([
            'name' => 'Event Product',
            'item_id' => 'EVENT-123',
            'store_hub_id' => $hub->id,
            'stock' => 12,
            'damaged_stock' => 3,
            'status' => 'active',
        ]);
        $path = EventPulloutWorkbook::create([[
            'name' => 'Event Product (DAMAGED)',
            'barcode' => '',
            'item_id' => 'EVENT-123',
            'physical_stock' => 15,
            'good_stock' => 12,
            'damaged_stock' => 3,
            'actual_pullout' => 1,
        ]], [
            'reference' => 'SW-EVENT-123',
            'occurred_on' => '2026-10-01',
            'source' => 'Book Fair',
            'notes' => 'Imported notes',
        ]);

        try {
            $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
                ->post(route('inventory-transactions.sponsor.worksheet.import'), [
                    'hub_id' => $hub->id,
                    'file' => new UploadedFile(
                        $path,
                        'event-pullout.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertOk()
                ->assertJsonPath('items.0.product_id', $product->id)
                ->assertJsonPath('items.0.quantity', 1)
                ->assertJsonPath('items.0.stock_condition', 'damaged')
                ->assertJsonPath('metadata.reference', 'SW-EVENT-123')
                ->assertJsonPath('metadata.occurred_on', '2026-10-01')
                ->assertJsonPath('metadata.source', 'Book Fair')
                ->assertJsonPath('metadata.notes', 'Imported notes');
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
