<?php

namespace Tests\Feature;

use App\Models\NationalProduct;
use App\Models\NationalPullout;
use App\Models\InventoryTransaction;
use App\Models\StoreHub;
use App\Models\User;
use App\Support\NationalPulloutWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class NationalPulloutTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_head_office_admin_and_inventory_staff_can_open_the_pullout_form(): void
    {
        [$headOffice, $branch] = $this->hubs();
        $product = $this->product();

        foreach (['admin', 'inventory_staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'hub_id' => $headOffice->id]);
            $this->actingAs($user)
                ->get(route('national-pullouts.create', ['hub_id' => $headOffice->id]))
                ->assertOk()
                ->assertSee('National Bookstore Pullout')
                ->assertDontSee('Download Print-Ready Excel Worksheet')
                ->assertSee('Import Items (Excel)')
                ->assertSee('Download Excel Worksheet')
                ->assertSee('id="downloadPulloutWorksheet"', false)
                ->assertSee('id="includePulloutWorksheetProducts" aria-label="Include all product items in the worksheet" checked', false)
                ->assertSee('Include all products in the downloaded template.')
                ->assertSee('Export Items (Excel)')
                ->assertSee('P.O. Number')
                ->assertSee('Items to Pull Out')
                ->assertSee('<th style="min-width:320px">Product Item Name</th>', false)
                ->assertSee('<th style="width:110px">Qty</th>', false)
                ->assertSee('<th style="width:150px">Actual Pull-out</th>', false)
                ->assertDontSee('<th>Unit Type</th>', false)
                ->assertDontSee('<th>Purpose</th>', false)
                ->assertSee('Actual Pull-out')
                ->assertSee($product->name);
        }

        $branchStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $branch->id]);
        $salesAssociate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $headOffice->id]);

        $this->actingAs($branchStaff)
            ->get(route('national-pullouts.create', ['hub_id' => $headOffice->id]))
            ->assertForbidden();
        $this->actingAs($salesAssociate)
            ->get(route('national-pullouts.create', ['hub_id' => $headOffice->id]))
            ->assertForbidden();

        $headOfficeStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $headOffice->id]);
        $transactionLogs = $this->actingAs($headOfficeStaff)
            ->get(route('inventory-transactions.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('Event operations')
            ->assertSee('National Bookstore Pullout')
            ->assertSee('Inventory updates');
        $transactionLogContent = $transactionLogs->getContent();
        $eventOperationsPosition = strpos($transactionLogContent, 'Event operations');
        $inventoryUpdatesPosition = strpos($transactionLogContent, 'Inventory updates');
        $this->assertNotFalse($eventOperationsPosition);
        $this->assertNotFalse($inventoryUpdatesPosition);
        $document = new \DOMDocument;
        @$document->loadHTML($transactionLogContent);
        $xpath = new \DOMXPath($document);
        $eventOperations = $xpath->query('//section[.//h2[normalize-space()="Event operations"]]');
        $this->assertSame(1, $eventOperations->length);
        $eventActionTitles = array_map(
            static fn (\DOMElement $title) => $title->textContent,
            iterator_to_array($xpath->query('.//a[contains(@class,"inventory-action-link")]//strong', $eventOperations->item(0)))
        );
        $this->assertSame(['Event', 'Fully Booked', 'National Bookstore Pullout'], $eventActionTitles);
        $this->assertStringNotContainsString('Event Inventory', $eventOperations->item(0)->textContent);
        $this->assertStringContainsString('activity_type=event', $transactionLogContent);
        $this->assertStringContainsString('return_to=transaction_logs', $transactionLogContent);
        $this->assertStringContainsString('activity_type=fully_booked', $transactionLogContent);
        $this->assertTrue($eventOperationsPosition < $inventoryUpdatesPosition);
        $this->actingAs($branchStaff)
            ->get(route('inventory-transactions.index', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertDontSee('Head Office only');
    }

    public function test_worksheet_route_downloads_the_excel_template(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $response = $this->actingAs($admin)->get(route('national-pullouts.worksheet', [
            'hub_id' => $headOffice->id,
        ]))->assertOk();

        $archive = new \PharData($response->getFile()->getPathname(), 0, null, \Phar::ZIP);
        $worksheet = $archive['xl/worksheets/sheet1.xml']->getContent();
        $this->assertStringContainsString($product->name, $worksheet);
        $this->assertStringContainsString('<c r="C3" s="4"/>', $worksheet);
        $this->assertStringContainsString('<c r="F3" s="4"/>', $worksheet);
    }

    public function test_excel_worksheet_has_the_requested_print_layout_and_product_columns(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $response = $this->actingAs($admin)->get(route('national-pullouts.excel-worksheet', [
            'hub_id' => $headOffice->id,
            'po_number' => 'PO-5001',
            'occurred_on' => '2026-10-13',
            'remarks' => 'National Bookstore replenishment',
        ]))->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->baseResponse->headers->get('Content-Type')
        );

        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $workbook = new \PharData($path, 0, null, \Phar::ZIP);
            foreach ([
                '[Content_Types].xml',
                '_rels/.rels',
                'xl/workbook.xml',
                'xl/_rels/workbook.xml.rels',
                'xl/styles.xml',
                'xl/worksheets/sheet1.xml',
            ] as $file) {
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($workbook[$file]->getContent()), "Invalid XML in {$file}.");
            }
            $sheet = $workbook['xl/worksheets/sheet1.xml']->getContent();
            $workbookXml = $workbook['xl/workbook.xml']->getContent();
            $styles = $workbook['xl/styles.xml']->getContent();
            $this->assertStringContainsString('paperSize="1" orientation="portrait" fitToWidth="1"', $sheet);
            $this->assertStringContainsString('left="0.6" right="0.6" top="0.6" bottom="0.6"', $sheet);
            $this->assertStringContainsString('width="9.5" customWidth="1"', $sheet);
            $this->assertStringContainsString('r="1" ht="45" customHeight="1"', $sheet);
            $this->assertStringContainsString('r="2" ht="35" customHeight="1"', $sheet);
            $this->assertStringContainsString('r="3" ht="25" customHeight="1"', $sheet);
            $this->assertStringContainsString('name="_xlnm.Print_Titles"', $workbookXml);
            $this->assertStringContainsString('dimension ref="A1:F3"', $sheet);
            $this->assertStringContainsString('<mergeCell ref="B1:C1"/>', $sheet);
            $this->assertStringContainsString('<mergeCell ref="E1:F1"/>', $sheet);
            $this->assertStringContainsString('NBS', $sheet);
            $this->assertStringContainsString('r="B1" s="3"', $sheet);
            $this->assertStringContainsString('P.O. #: PO-5001', $sheet);
            $this->assertStringContainsString('Date: 2026-10-13', $sheet);
            $this->assertStringContainsString('Remarks: National Bookstore replenishment', $sheet);
            $this->assertStringContainsString('r="A2" s="2"', $sheet);
            $this->assertStringContainsString('r="B2" s="2"', $sheet);
            $this->assertStringContainsString('Item ID', $sheet);
            $this->assertStringContainsString('Product Item Name', $sheet);
            $this->assertStringContainsString('Qty', $sheet);
            $this->assertStringContainsString('Purpose', $sheet);
            $this->assertStringContainsString('Physical Stocks', $sheet);
            $this->assertStringContainsString('Actual Pull-out', $sheet);
            $this->assertStringNotContainsString('Unit Type', $sheet);
            $this->assertStringContainsString('r="B3"', $sheet);
            $this->assertStringContainsString($product->name, $sheet);
            $this->assertStringContainsString($product->item_id.'</t>', $sheet);
            $this->assertStringContainsString('<c r="C3" s="4"/>', $sheet);
            $this->assertStringContainsString('<c r="F3" s="4"/>', $sheet);
            $this->assertStringContainsString('wrapText="1"', $styles);

            $headerOnly = $this->actingAs($admin)->get(route('national-pullouts.excel-worksheet', [
                'hub_id' => $headOffice->id,
                'include_products' => 0,
            ]))->assertOk();
            $headerOnlyPath = $headerOnly->baseResponse->getFile()->getPathname();
            $headerOnlyWorkbook = new \PharData($headerOnlyPath, 0, null, \Phar::ZIP);
            $headerOnlySheet = $headerOnlyWorkbook['xl/worksheets/sheet1.xml']->getContent();
            $this->assertStringContainsString('dimension ref="A1:F12"', $headerOnlySheet);
            $this->assertStringContainsString('<row r="3" ht="25" customHeight="1">', $headerOnlySheet);
            $this->assertStringContainsString('<c r="A3" s="1"/>', $headerOnlySheet);
            $this->assertStringContainsString('<c r="B3" s="1"/>', $headerOnlySheet);
            $this->assertStringNotContainsString($product->name, $headerOnlySheet);
            unset($headerOnlyWorkbook);
            unlink($headerOnlyPath);
        } finally {
            unset($workbook);
            unlink($path);
        }
    }

    public function test_import_button_opens_picker_and_only_shows_requested_product_columns(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $this->actingAs($admin)
            ->get(route('national-pullouts.create', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('id="pulloutImportLoading" class="national-pullout-import-overlay"', false)
            ->assertSee('id="pulloutImportProgress"', false)
            ->assertSee("request.upload.addEventListener('progress'", false)
            ->assertSee("window.AppAlert.show(", false)
            ->assertSee("'Pullout items imported'", false)
            ->assertSee('for="pulloutWorksheetFile"', false)
            ->assertSee('.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', false)
            ->assertSee("dataList.id = 'nationalPulloutProductOptions'", false)
            ->assertSee('list="nationalPulloutProductOptions"', false)
            ->assertSee('const productLabel = product =>', false)
            ->assertSee('const productsByLabel = new Map', false)
            ->assertSee('option.value = productLabel(product);', false)
            ->assertSee("const selectedProductIds = excludedRow", false)
            ->assertSee('const updateProductSuggestions = (excludedRow = null)', false)
            ->assertSee('.filter(product => !selectedIds.has(String(product.id)))', false)
            ->assertSee("search.addEventListener('focus', () => updateProductSuggestions(row));", false)
            ->assertSee("search.setCustomValidity('Select a product from the search suggestions.')", false)
            ->assertSee('/inventory-transactions/national-pullout/worksheet/excel/export', false)
            ->assertSee('/inventory-transactions/national-pullout/worksheet/excel/import', false)
            ->assertDontSee('pullout-product-results')
            ->assertDontSee('already included in Items to Pull Out')
            ->assertDontSee('pullout-export-check')
            ->assertDontSee('pullout-unit-type')
            ->assertDontSee('pullout-purpose')
            ->assertDontSee('pullout-physical-stock');
    }

    public function test_excel_worksheet_can_be_imported_with_document_and_item_values(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();
        $path = \App\Support\NationalPulloutWorkbook::create([[
            'name' => $product->name,
            'quantity' => 5,
            'purpose' => 'National Bookstore Pullout',
            'physical_stock' => 10,
            'actual_pullout' => 3,
        ]], [
            'po_number' => 'PO-IMPORT-1',
            'occurred_on' => '2026-10-13',
            'remarks' => 'Imported worksheet',
        ]);

        try {
            $file = new UploadedFile($path, 'national-pullout.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->actingAs($admin)->postJson(route('national-pullouts.import-excel-worksheet'), [
                'hub_id' => $headOffice->id,
                'worksheet' => $file,
            ])->assertOk()->assertExactJson([
                'po_number' => 'PO-IMPORT-1',
                'occurred_on' => '2026-10-13',
                'remarks' => 'Imported worksheet',
                'items' => [[
                    'name' => $product->name,
                    'quantity' => '5',
                    'actual_pullout' => '3',
                ]],
            ]);
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    public function test_exporting_current_items_uses_the_excel_worksheet_template(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $response = $this->actingAs($admin)->post(route('national-pullouts.export-excel-worksheet'), [
            'hub_id' => $headOffice->id,
            'po_number' => 'PO-EXPORT-1',
            'occurred_on' => '2026-10-13',
            'remarks' => 'Current form items',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'actual_pullout' => 3,
            ]],
        ])->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->baseResponse->headers->get('Content-Type')
        );
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $workbook = new \PharData($path, 0, null, \Phar::ZIP);
            $sheet = $workbook['xl/worksheets/sheet1.xml']->getContent();
            $this->assertStringContainsString('P.O. #: PO-EXPORT-1', $sheet);
            $this->assertStringContainsString($product->name, $sheet);
            $this->assertStringContainsString('<v>5</v>', $sheet);
            $this->assertStringContainsString('<v>3</v>', $sheet);
        } finally {
            unset($workbook);
            unlink($path);
        }
    }

    public function test_saving_a_pullout_deducts_actual_quantity_and_appears_in_transaction_logs(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $this->actingAs($admin)
            ->post(route('national-pullouts.store'), [
                'hub_id' => $headOffice->id,
                'po_number' => ' PO-1001 ',
                'occurred_on' => '2026-10-09',
                'remarks' => 'For National Bookstore replenishment.',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'actual_pullout' => 3,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory-transactions.index', [
                'hub_id' => $headOffice->id,
                'type' => 'national_bookstore_pullout',
                'national_pullout_saved' => 1,
                'reference' => 'PO-1001',
            ]));

        $pullout = NationalPullout::with('items')->sole();
        $this->assertSame('PO-1001', $pullout->po_number);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(10, $pullout->items->sole()->physical_stock);
        $this->assertSame(3, $pullout->items->sole()->actual_pullout);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', [
                'hub_id' => $headOffice->id,
                'type' => 'national_bookstore_pullout',
            ]))
            ->assertOk()
            ->assertSee('National Bookstore Pullout')
            ->assertSee('P.O. # PO-1001')
            ->assertSee('Download Excel');

        $export = $this->actingAs($admin)
            ->get(route('national-pullouts.export', [
                'nationalPullout' => $pullout,
                'hub_id' => $headOffice->id,
            ]))
            ->assertOk();
        $file = UploadedFile::fake()->createWithContent('pullout-export.xlsx', file_get_contents($export->getFile()->getPathname()));
        $workbook = NationalPulloutWorkbook::read($file->getRealPath());
        $this->assertSame('PO-1001', $workbook['po_number']);
        $this->assertSame('2026-10-09', $workbook['occurred_on']);
        $this->assertSame('For National Bookstore replenishment.', $workbook['remarks']);
        $this->assertSame($product->name, $workbook['items'][0]['name']);
        $this->assertSame('5', $workbook['items'][0]['quantity']);
        $this->assertSame('3', $workbook['items'][0]['actual_pullout']);
    }

    public function test_invalid_pullout_is_atomic_and_duplicate_po_numbers_are_rejected(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $payload = [
            'hub_id' => $headOffice->id,
            'po_number' => 'PO-2001',
            'occurred_on' => '2026-10-09',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'actual_pullout' => 6,
            ]],
        ];
        $this->actingAs($admin)->post(route('national-pullouts.store'), $payload)
            ->assertSessionHasErrors('items.0.actual_pullout');
        $this->assertDatabaseCount('national_pullouts', 0);
        $this->assertSame(10, $product->fresh()->stock);

        $payload['items'][0]['actual_pullout'] = 2;
        $this->actingAs($admin)->post(route('national-pullouts.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame(8, $product->fresh()->stock);

        $payload['po_number'] = ' PO-2001 ';
        $this->actingAs($admin)->post(route('national-pullouts.store'), $payload)
            ->assertSessionHasErrors('po_number');
        $this->assertDatabaseCount('national_pullouts', 1);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_national_bookstore_pullout_can_be_returned_by_po_number_to_national_inventory(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();
        $product->update(['stock' => 7]);
        $pullout = NationalPullout::create([
            'po_number' => 'PO-RETURN-1',
            'occurred_on' => '2026-10-10',
            'store_hub_id' => $headOffice->id,
            'created_by' => $admin->id,
        ]);
        $pulloutItem = $pullout->items()->create([
            'national_product_id' => $product->id,
            'item_id' => $product->item_id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_type' => $product->unit_type,
            'purpose' => 'National Bookstore Pullout',
            'physical_stock' => 10,
            'actual_pullout' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.return.create', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('National Bookstore')
            ->assertDontSee('National Bookstore Pull-out')
            ->assertSee('national_pullout_id', false);

        $this->actingAs($admin)->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $headOffice->id,
            'channel' => 'national_bookstore',
        ]))->assertOk()->assertJsonPath('orders.0.order_number', 'PO-RETURN-1');

        $this->actingAs($admin)->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $headOffice->id,
            'channel' => 'national_bookstore',
            'transaction_id' => $pullout->id,
        ]))->assertOk()
            ->assertJsonPath('transaction.order_number', 'PO-RETURN-1')
            ->assertJsonPath('transaction.items.0.national_pullout_item_id', $pulloutItem->id)
            ->assertJsonPath('transaction.items.0.quantity', 3)
            ->assertJsonPath('transaction.items.0.returned_quantity', 0);

        $payload = [
            'type' => 'return',
            'store_hub_id' => $headOffice->id,
            'occurred_on' => '2026-10-13',
            'channel' => 'national_bookstore',
            'reference' => (string) $pullout->id,
            'national_pullout_id' => $pullout->id,
            'items' => [[
                'national_pullout_item_id' => $pulloutItem->id,
                'national_product_id' => $product->id,
                'good_quantity' => 1,
                'damaged_quantity' => 1,
            ]],
        ];
        $this->actingAs($admin)->post(route('inventory-transactions.return.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory-transactions.index', [
                'hub_id' => $headOffice->id,
                'type' => 'return',
            ]));

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(1, $product->fresh()->damaged_stock);
        $returns = InventoryTransaction::where('channel', 'national_bookstore')->get();
        $this->assertCount(2, $returns);
        $this->assertSame(['damaged', 'good'], $returns->pluck('condition')->sort()->values()->all());
        $this->assertSame(2, $returns->sum('quantity'));
        $returns->each(function (InventoryTransaction $return) use ($pullout, $pulloutItem, $product) {
            $this->assertNull($return->product_id);
            $this->assertSame($pullout->id, $return->national_pullout_id);
            $this->assertSame($pulloutItem->id, $return->national_pullout_item_id);
            $this->assertSame($product->id, $return->national_product_id);
            $this->assertSame('PO-RETURN-1', $return->reference);
        });

        $payload['items'][0]['good_quantity'] = 2;
        $this->actingAs($admin)->post(route('inventory-transactions.return.store'), $payload)
            ->assertSessionHasErrors('items');
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(1, $product->fresh()->damaged_stock);

        $this->actingAs($admin)->get(route('national-inventory.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('Good Items')
            ->assertSee('Damaged Items')
            ->assertSee('Total Physical Stock')
            ->assertSee('<td class="text-center fw-bold national-total-stock">9</td>', false)
            ->assertViewHas('nationalProducts', fn ($products) => (int) $products->first()->stock === 8
                && (int) $products->first()->damaged_stock === 1);

        $this->actingAs($admin)->get(route('inventory-transactions.index', [
            'hub_id' => $headOffice->id,
            'type' => 'return',
        ]))->assertOk()
            ->assertSee('Return P.O. #')
            ->assertSee('PO-RETURN-1')
            ->assertSee($product->name);
    }

    private function hubs(): array
    {
        return [
            StoreHub::create(['name' => 'AC Head Office', 'code' => 'AC-HO', 'status' => 'active', 'is_head_office' => true]),
            StoreHub::create(['name' => 'AC Branch', 'code' => 'AC-BR', 'status' => 'active', 'is_head_office' => false]),
        ];
    }

    private function product(): NationalProduct
    {
        return NationalProduct::create([
            'item_id' => '1',
            'name' => 'National Acrylic Paint',
            'barcode' => '900000001',
            'brand' => 'ArtCo',
            'unit_type' => 'PCS',
            'stock' => 10,
            'status' => 'active',
        ]);
    }

    private function csvRows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);

        return array_map('str_getcsv', preg_split('/\r\n|\r|\n/', rtrim($csv)));
    }
}
