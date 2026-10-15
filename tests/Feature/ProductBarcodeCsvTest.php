<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
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
        $this->assertMatchesRegularExpression('/filename=products_TEST_\d{8}_\d{6}_\d+\.csv/', $exportResponse->headers->get('Content-Disposition'));
        ob_start();
        $exportResponse->sendContent();
        $csv = ob_get_clean();
        $lines = explode("\n", $csv);
        $row = str_getcsv($lines[1]);
        $this->assertSame('="00001"', $row[1]);
        $this->assertSame('="007661234567891234567890"', $row[4]);
        $this->post(route('hub.products.import', $hub), ['file' => UploadedFile::fake()->createWithContent('roundtrip.csv', $csv)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('007661234567891234567890', $product->fresh()->barcode);
        $this->assertSame('00001', $product->fresh()->item_id);
    }

    public function test_scientific_notation_rejects_entire_import_before_writes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST', 'status' => 'active']);
        $csv = "ID,Item ID,Name,Description,Barcode\n1,SKU-1,Brush,Brush,001234\n2,SKU-2,Paint,Paint,7.66e+11\n";
        $this->post(route('hub.products.import', $hub), ['file' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
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

        $csvResponse = $this->get(route('hub.products.export', [
            'hub' => $hub->id,
            'product_ids' => [$product->id],
            'format' => 'csv',
        ]))->assertOk();
        $csv = ltrim($csvResponse->streamedContent(), "\xEF\xBB\xBF");
        $csvLines = preg_split('/\r\n|\r|\n/', $csv);
        $csvHeaders = str_getcsv($csvLines[0]);
        $csvRow = str_getcsv($csvLines[1]);
        $this->assertNotContains('ID', $csvHeaders);
        $this->assertSame('Item ID', $csvHeaders[0]);
        $this->assertSame('="000012345678901234567890"', $csvRow[0]);
        $this->assertSame('="00007661234567891234567890"', $csvRow[3]);
        $this->post(route('hub.products.import', $hub), [
            'file' => UploadedFile::fake()->createWithContent('head-office-roundtrip.csv', $csv),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('000012345678901234567890', $product->fresh()->item_id);
        $this->assertSame('00007661234567891234567890', $product->fresh()->barcode);

        $response = $this->get(route('hub.products.export', [
            'hub' => $hub->id,
            'product_ids' => [$product->id],
            'format' => 'xlsx',
        ]))->assertOk()->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
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

            $this->assertStringContainsString('<c r="A1" s="1" t="inlineStr"><is><t xml:space="preserve">Item ID</t></is></c>', $worksheet);
            $this->assertStringNotContainsString('xml:space="preserve">ID</t>', $worksheet);
            $this->assertStringContainsString(
                '<c r="A2" s="2" t="inlineStr"><is><t xml:space="preserve">000012345678901234567890</t></is></c>',
                $worksheet
            );
            $this->assertStringContainsString(
                '<c r="D2" s="2" t="inlineStr"><is><t xml:space="preserve">00007661234567891234567890</t></is></c>',
                $worksheet
            );
            $this->assertStringContainsString('numFmtId="49"', $styles);
        } finally {
            @unlink($workbookPath);
        }
    }

    public function test_inventory_worksheet_exports_barcode_and_item_id_as_excel_safe_csv_text(): void
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
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = preg_split('/\r\n|\n|\r/', ltrim($response->streamedContent(), "\xEF\xBB\xBF"));
        $this->assertSame('="00007661234567891234567890"', str_getcsv($lines[1])[1]);
        $this->assertSame('="000012345678901234567890"', str_getcsv($lines[1])[2]);
    }
}
