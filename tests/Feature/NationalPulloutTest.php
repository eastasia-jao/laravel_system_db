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
                ->assertSee('Import Items (CSV)')
                ->assertSee('Download CSV Worksheet')
                ->assertSee('Export Selected Items')
                ->assertSee('P.O. Number')
                ->assertSee('ITEMS TO PULL-OUT')
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

    public function test_worksheet_has_printable_metadata_and_only_the_requested_item_columns(): void
    {
        [$headOffice] = $this->hubs();
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $headOffice->id]);
        $product = $this->product();

        $response = $this->actingAs($admin)->get(route('national-pullouts.worksheet', [
            'hub_id' => $headOffice->id,
        ]))->assertOk();

        $rows = $this->csvRows($response->streamedContent());
        $this->assertSame(['P.O. #:', ''], $rows[0]);
        $this->assertSame(['Date:', ''], $rows[1]);
        $this->assertSame(['Remarks:', ''], $rows[2]);
        $this->assertSame([null], $rows[3]);
        $this->assertSame(
            ['Product Item Name', 'Qty', 'Unit Type', 'Purpose', 'Physical Stocks', 'Actual Pull-out'],
            $rows[4]
        );
        $this->assertSame([$product->name, '', 'PCS', '', '10', ''], $rows[5]);
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
                    'purpose' => 'Store replenishment',
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
            ->assertSee('Store replenishment')
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
        $this->assertSame([$product->name, '5', 'PCS', 'Store replenishment', '10', '3'], $rows[5]);
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
                'purpose' => 'Pull-out',
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
