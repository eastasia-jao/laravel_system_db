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
        $stylesXml = $archive['xl/styles.xml']->getContent();
        unset($archive);
        $this->assertStringContainsString('<mergeCell ref="B1:C1"/>', $worksheetXml);
        $this->assertStringContainsString('<mergeCell ref="E1:F1"/>', $worksheetXml);
        $this->assertStringContainsString('<mergeCell ref="G1:H1"/>', $worksheetXml);
        $this->assertStringContainsString('<dimension ref="A1:I3"/>', $worksheetXml);
        $this->assertStringContainsString('<row r="1" ht="20.1" customHeight="1">', $worksheetXml);
        $this->assertStringContainsString('<row r="2" ht="30" customHeight="1">', $worksheetXml);
        $this->assertStringContainsString('<row r="3" ht="20.1" customHeight="1">', $worksheetXml);
        $this->assertStringContainsString('<pageMargins left="0.6" right="0.6" top="0.6" bottom="0" header="0" footer="0"/>', $worksheetXml);
        $this->assertStringContainsString('<pageSetup paperSize="1" orientation="portrait" fitToWidth="1" fitToHeight="0"/>', $worksheetXml);
        $this->assertStringContainsString('<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>', $worksheetXml);
        $this->assertStringContainsString('<font><b/><sz val="10"/><name val="Arial"/></font>', $stylesXml);
        $this->assertStringContainsString('<fgColor rgb="FFD92D20"/>', $stylesXml);
        $this->assertStringContainsString('<fgColor rgb="FFF1F5F9"/>', $stylesXml);
        $this->assertStringContainsString('<color rgb="FF64748B"/>', $stylesXml);
        $this->assertStringContainsString('<c r="A2" s="3"', $worksheetXml);
        $this->assertStringContainsString('<c r="A1" s="1"', $worksheetXml);
        $this->assertStringContainsString('<c r="B1" s="2"', $worksheetXml);
        $this->assertStringContainsString('Date: 2026-10-01', $worksheetXml);
        $this->assertStringContainsString('<c r="E1" s="2"', $worksheetXml);
        $this->assertStringContainsString('<c r="G1" s="2"', $worksheetXml);
        $this->assertStringContainsString('fontId="1" fillId="0" borderId="1"', $stylesXml);
        $this->assertStringContainsString('<c r="B2" s="3"', $worksheetXml);
        $this->assertStringContainsString('<c r="B3" s="4"', $worksheetXml);
        $this->assertStringContainsString('<c r="C3" s="5"', $worksheetXml);
        $this->assertStringContainsString('<c r="D3" s="5"', $worksheetXml);
        $this->assertStringContainsString('<c r="G2" s="3"', $worksheetXml);
        $this->assertStringContainsString('<c r="H2" s="3"', $worksheetXml);
        $this->assertStringContainsString('<c r="I2" s="3"', $worksheetXml);
        $this->assertStringContainsString('min="9" max="9" width="0" hidden="1"', $worksheetXml);
        $this->assertSame('', $workbook['items'][0]['pullout_qty']);
        $this->assertSame('', $workbook['items'][0]['actual_pullout']);
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
            'pullout_qty' => '',
            'actual_pullout' => '',
            'stock_condition' => '',
        ]], $workbook['items']);
        $this->get(route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Import Items (Excel / CSV)')
            ->assertSee('id="transactionImportLoading" class="transaction-import-overlay"', false)
            ->assertSee('Getting your items ready')
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
                    ['product_id' => $product->id, 'stock_condition' => 'good'],
                    ['product_id' => $product->id, 'stock_condition' => 'damaged'],
                ],
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbook = EventPulloutWorkbook::read($response->baseResponse->getFile()->getPathname());
        $this->assertSame([
            ['name' => 'Event Product', 'pullout_qty' => '', 'quantity' => '', 'stock_condition' => 'good'],
            ['name' => 'Event Product', 'pullout_qty' => '', 'quantity' => '', 'stock_condition' => 'damaged'],
        ], array_map(
            fn ($item) => [
                'name' => $item['name'],
                'pullout_qty' => $item['pullout_qty'],
                'quantity' => $item['actual_pullout'],
                'stock_condition' => $item['stock_condition'],
            ],
            $workbook['items']
        ));
        $this->assertStringNotContainsString('(GOOD)', $workbook['items'][0]['name']);
        $this->assertStringNotContainsString('(DAMAGED)', $workbook['items'][1]['name']);
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
            'name' => 'Event Product',
            'barcode' => '',
            'item_id' => 'EVENT-123',
            'physical_stock' => 15,
            'good_stock' => 12,
            'damaged_stock' => 3,
            'pullout_qty' => 5,
            'actual_pullout' => 1,
            'stock_condition' => 'damaged',
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
                ->assertJsonPath('items.0.pullout_qty', 5)
                ->assertJsonPath('items.0.stock_condition', 'damaged')
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
