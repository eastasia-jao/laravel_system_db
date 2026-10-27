<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
use App\Support\ProductExportWorkbook;
use App\Support\XlsxTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Phar;
use PharData;
use Tests\TestCase;

class ProductBarcodeCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_import_preserves_full_identifiers_and_renames_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST', 'status' => 'active']);
        $product = Product::create(['name' => 'Brush', 'item_id' => '00001', 'barcode' => '007661234567891234567890', 'store_hub_id' => $hub->id, 'stock' => 8, 'status' => 'active']);
        $exportResponse = $this->get(route('hub.products.export', ['hub' => $hub->id, 'product_ids' => [$product->id]]))->assertOk();
        $this->assertMatchesRegularExpression('/filename="products_TEST_\d{8}_\d{6}_\d+\.xlsx"/', $exportResponse->headers->get('Content-Disposition'));
        $workbook = UploadedFile::fake()->createWithContent('roundtrip.xlsx', $exportResponse->getContent());
        $rows = XlsxTable::read($workbook->getRealPath());
        $this->assertSame('00001', $rows[1][1]);
        $this->assertSame('007661234567891234567890', $rows[1][4]);
        $this->post(route('hub.products.import', $hub), ['file' => $workbook])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('007661234567891234567890', $product->fresh()->barcode);
        $this->assertSame('00001', $product->fresh()->item_id);
    }

    public function test_scientific_notation_rejects_entire_import_before_writes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST', 'status' => 'active']);
        $rows = [
            ['ID', 'Item ID', 'Name', 'Description', 'Barcode'],
            ['1', 'SKU-1', 'Brush', 'Brush', '001234'],
            ['2', 'SKU-2', 'Paint', 'Paint', '7.66e+11'],
        ];
        $this->post(route('hub.products.import', $hub), ['file' => UploadedFile::fake()->createWithContent('products.xlsx', ProductExportWorkbook::fromRows($rows))])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('staff_activity_logs', 0);
    }

    public function test_excel_export_keeps_item_id_and_barcode_as_exact_text(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create([
            'name' => 'Brush',
            'item_id' => '000012345678901234567890',
            'barcode' => '00007661234567891234567890',
            'store_hub_id' => $hub->id,
            'stock' => 8,
            'status' => 'active',
        ]);

        $response = $this->get(route('hub.products.export', [
            'hub' => $hub->id,
            'product_ids' => [$product->id],
            'format' => 'xlsx',
        ]))->assertOk()->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $roundTrip = UploadedFile::fake()->createWithContent('head-office-roundtrip.xlsx', $response->getContent());
        $this->post(route('hub.products.import', $hub), ['file' => $roundTrip])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('000012345678901234567890', $product->fresh()->item_id);
        $this->assertSame('00007661234567891234567890', $product->fresh()->barcode);
        $this->assertMatchesRegularExpression(
            '/filename="products_TEST_\d{8}_\d{6}_\d+\.xlsx"/',
            $response->headers->get('Content-Disposition')
        );

        $temporaryBase = tempnam(sys_get_temp_dir(), 'product-export-');
        $workbookPath = $temporaryBase.'.xlsx';
        @unlink($temporaryBase);
        file_put_contents($workbookPath, $response->getContent());

        try {
            $archive = new PharData($workbookPath, 0, null, Phar::ZIP);
            $worksheet = $archive['xl/worksheets/sheet1.xml']->getContent();
            $styles = $archive['xl/styles.xml']->getContent();
            unset($archive);

            $this->assertStringContainsString('<c r="A1" s="1" t="inlineStr"><is><t xml:space="preserve">ID</t></is></c>', $worksheet);
            $this->assertStringContainsString('<c r="B1" s="1" t="inlineStr"><is><t xml:space="preserve">Item ID</t></is></c>', $worksheet);
            $this->assertStringContainsString('<c r="A2" s="0" t="inlineStr"><is><t xml:space="preserve"></t></is></c>', $worksheet);
            $this->assertStringContainsString(
                '<c r="B2" s="2" t="inlineStr"><is><t xml:space="preserve">000012345678901234567890</t></is></c>',
                $worksheet
            );
            $this->assertStringContainsString(
                '<c r="E2" s="2" t="inlineStr"><is><t xml:space="preserve">00007661234567891234567890</t></is></c>',
                $worksheet
            );
            $this->assertStringNotContainsString('<autoFilter', $worksheet);
            $this->assertStringContainsString('numFmtId="49"', $styles);
        } finally {
            @unlink($workbookPath);
        }
    }

    public function test_inventory_worksheet_exports_barcode_and_item_id_as_excel_text(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST', 'status' => 'active']);
        Product::create([
            'name' => 'Brush',
            'item_id' => '000012345678901234567890',
            'barcode' => '00007661234567891234567890',
            'store_hub_id' => $hub->id,
            'stock' => 8,
            'status' => 'active',
        ]);

        $response = $this->get(route('inventory-transactions.product-worksheet', [
            'hub_id' => $hub->id,
            'type' => 'restock',
        ]))->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file = UploadedFile::fake()->createWithContent('inventory.xlsx', $response->getContent());
        $rows = XlsxTable::read($file->getRealPath());
        $this->assertSame('00007661234567891234567890', $rows[1][1]);
        $this->assertSame('000012345678901234567890', $rows[1][2]);
    }
}
