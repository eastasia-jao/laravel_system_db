<?php

namespace Tests\Feature;

use App\Models\NationalProduct;
use App\Models\NationalPullout;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->assertSee('Download Print-Ready Excel Worksheet')
                ->assertSee('Import Items (CSV)')
                ->assertSee('Download CSV Worksheet')
                ->assertSee('Export Selected Items')
                ->assertSee('P.O. Number')
                ->assertSee('ITEMS TO PULL-OUT')
                ->assertSee('Physical Stocks')
                ->assertSee('Unit Type')
                ->assertSee('Purpose')
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
        $this->actingAs($headOfficeStaff)
            ->get(route('inventory-transactions.index', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('National Bookstore Pullout');
        $this->actingAs($branchStaff)
            ->get(route('inventory-transactions.index', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertDontSee('Head Office only');
    }

    public function test_csv_worksheet_has_only_product_columns_and_current_national_stock(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $response = $this->actingAs($admin)->get(route('national-pullouts.worksheet', [
            'hub_id' => $headOffice->id,
        ]))->assertOk();

        $rows = $this->csvRows($response->streamedContent());
        $this->assertSame(
            ['Product Item Name', 'Qty', 'Unit Type', 'Purpose', 'Physical Stocks', 'Actual Pull-out'],
            $rows[0]
        );
        $this->assertSame([$product->name, '', 'PCS', 'National Bookstore Pullout', '10', ''], $rows[1]);
        $this->assertNotContains('P.O. #:', array_column($rows, 0));
        $this->assertNotContains('Date:', array_column($rows, 0));
        $this->assertNotContains('Remarks:', array_column($rows, 0));
    }

    public function test_excel_worksheet_has_the_requested_print_layout_and_product_columns(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $response = $this->actingAs($admin)->get(route('national-pullouts.excel-worksheet', [
            'hub_id' => $headOffice->id,
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
            $this->assertStringContainsString('left="0.6" right="0" top="0" bottom="0"', $sheet);
            $this->assertStringContainsString('width="9.5" customWidth="1"', $sheet);
            $this->assertStringContainsString('ht="20" customHeight="1"', $sheet);
            $this->assertStringContainsString('name="_xlnm.Print_Titles"', $workbookXml);
            $this->assertStringContainsString('Actual Pull-out', $sheet);
            $this->assertStringContainsString('Physical Stocks', $sheet);
            $this->assertStringContainsString('Purpose', $sheet);
            $this->assertStringContainsString('r="A4"', $sheet);
            $this->assertStringContainsString($product->name, $sheet);
            $this->assertStringContainsString('<c r="H4" s="4" t="n"><v>10</v></c>', $sheet);
            $this->assertStringContainsString('National Bookstore Pullout', $sheet);
            $this->assertStringContainsString('wrapText="1"', $styles);
        } finally {
            unset($workbook);
            unlink($path);
        }
    }

    public function test_pullout_search_options_include_national_unit_type_and_stock(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $this->actingAs($admin)
            ->get(route('national-pullouts.create', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('Physical Stocks')
            ->assertViewHas('productOptions', fn ($options) => $options->first()['stock'] === 10
                && $options->first()['unit_type'] === 'PCS');
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
            ->assertSee('Download CSV');

        $export = $this->actingAs($admin)
            ->get(route('national-pullouts.export', [
                'nationalPullout' => $pullout,
                'hub_id' => $headOffice->id,
            ]))
            ->assertOk();
        $rows = $this->csvRows($export->streamedContent());
        $this->assertSame(['P.O. #:', 'PO-1001'], $rows[0]);
        $this->assertSame(['Date:', '2026-10-09'], $rows[1]);
        $this->assertSame(['Remarks:', 'For National Bookstore replenishment.'], $rows[2]);
        $this->assertSame(
            [$product->name, '5', 'PCS', 'National Bookstore Pullout', '10', '3'],
            $rows[5]
        );
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
