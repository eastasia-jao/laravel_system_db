<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\PendingSale;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnWorkflowPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_sale_can_be_verified_then_returned_and_excess_returns_are_rejected(): void
    {
        $branch = StoreHub::create([
            'name' => 'Pilot Branch',
            'code' => 'PILOT-BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $salesAssociate = User::factory()->create([
            'role' => 'sales_associate',
            'hub_id' => $branch->id,
        ]);
        $inventoryStaff = User::factory()->create([
            'role' => 'inventory_staff',
            'hub_id' => $branch->id,
        ]);
        $product = Product::create([
            'name' => 'Pilot Return Product',
            'item_id' => 'PILOT-RETURN-001',
            'sales_price' => 100,
            'store_hub_id' => $branch->id,
            'stock' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($salesAssociate)
            ->post(route('sales.storeMultiChannelSale'), [
                'store_hub_id' => $branch->id,
                'sales_channel' => 'walk_in',
                'placed_order_date' => '2026-10-01',
                'customer_name' => 'Pilot Customer',
                'walkin_mop' => 'CASH',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $pendingSale = PendingSale::sole();
        $this->assertSame(10, (int) $product->fresh()->stock);

        $this->actingAs($inventoryStaff)
            ->post(route('sales.confirmPending', $pendingSale))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $sale = SalesTransaction::with('items')->sole();
        $item = $sale->items->sole();
        $this->assertSame('confirmed', $pendingSale->fresh()->status);
        $this->assertSame(8, (int) $product->fresh()->stock);

        $returnRoute = route('inventory-transactions.return.store');
        $returnData = [
            'type' => 'return',
            'store_hub_id' => $branch->id,
            'occurred_on' => '2026-10-01',
            'channel' => 'walk_in',
            'reference' => $sale->order_number,
            'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id,
                'transaction_item_id' => $item->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ];

        $this->actingAs($salesAssociate)
            ->post($returnRoute, $returnData)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Return items recorded successfully.');

        $this->assertSame(9, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'return',
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'created_by' => $salesAssociate->id,
            'channel' => 'walk_in',
            'condition' => 'good',
            'quantity' => 1,
        ]);

        $this->actingAs($salesAssociate)
            ->post($returnRoute, array_replace_recursive($returnData, [
                'items' => [[
                    'good_quantity' => 0,
                    'damaged_quantity' => 1,
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(2, InventoryTransaction::where('type', 'return')
            ->where('transaction_item_id', $item->id)
            ->sum('quantity'));

        $this->actingAs($salesAssociate)
            ->post($returnRoute, array_replace_recursive($returnData, [
                'items' => [[
                    'good_quantity' => 1,
                    'damaged_quantity' => 0,
                ]],
            ]))
            ->assertSessionHasErrors('items');

        $lookup = $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $branch->id,
            'channel' => 'walk_in',
            'transaction_id' => $sale->id,
        ]));

        $lookup->assertOk()
            ->assertJsonPath('transaction.id', $sale->id)
            ->assertJsonPath('transaction.items.0.returned_quantity', 2);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(2, InventoryTransaction::where('type', 'return')
            ->where('transaction_item_id', $item->id)
            ->sum('quantity'));
    }

    public function test_inventory_staff_can_select_any_active_store_when_recording_returns_without_sidebar_return_link(): void
    {
        $assignedBranch = StoreHub::create([
            'name' => 'Assigned Inventory Branch',
            'code' => 'INV-ASSIGNED',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $otherBranch = StoreHub::create([
            'name' => 'Other Inventory Branch',
            'code' => 'INV-OTHER',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $assignedBranch->id]);
        $product = Product::create([
            'name' => 'Other Branch Return Product',
            'item_id' => 'INV-OTHER-RETURN',
            'store_hub_id' => $otherBranch->id,
            'stock' => 4,
            'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $otherBranch->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-10-06',
            'order_number' => 'INV-OTHER-RETURN-001',
            'customer_name' => 'Other Branch Customer',
            'status' => 'confirmed',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $this->actingAs($staff)
            ->get(route('inventory-transactions.return.create', ['hub_id' => $otherBranch->id]))
            ->assertOk()
            ->assertSee('<select name="store_hub_id" class="form-select" required', false)
            ->assertSee('value="'.$assignedBranch->id.'"', false)
            ->assertSee('value="'.$otherBranch->id.'"', false)
            ->assertDontSee('href="'.route('inventory-transactions.return.create', ['hub_id' => $otherBranch->id]).'"', false);

        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $otherBranch->id,
            'channel' => 'walk_in',
            'transaction_id' => $sale->id,
        ]))->assertOk()
            ->assertJsonPath('transaction.id', $sale->id);

        $this->post(route('inventory-transactions.return.store'), [
            'type' => 'return',
            'store_hub_id' => $otherBranch->id,
            'occurred_on' => '2026-10-06',
            'channel' => 'walk_in',
            'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id,
                'transaction_item_id' => $item->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Return items recorded successfully.');

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'return',
            'store_hub_id' => $otherBranch->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'created_by' => $staff->id,
            'quantity' => 1,
        ]);
    }

    public function test_return_actions_are_limited_to_authorized_roles_hubs_and_channels(): void
    {
        $assignedBranch = StoreHub::create([
            'name' => 'Assigned Branch',
            'code' => 'ASSIGNED-BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $otherBranch = StoreHub::create([
            'name' => 'Other Branch',
            'code' => 'OTHER-BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $salesAssociate = User::factory()->create([
            'role' => 'sales_associate',
            'hub_id' => $assignedBranch->id,
        ]);
        $inventoryStaff = User::factory()->create([
            'role' => 'inventory_staff',
            'hub_id' => $assignedBranch->id,
        ]);
        $salesMarketingStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $assignedBranch->id,
            'sales_channels' => ['online'],
        ]);
        $product = Product::create([
            'name' => 'Role Test Product',
            'item_id' => 'ROLE-RETURN-001',
            'store_hub_id' => $assignedBranch->id,
            'stock' => 5,
            'status' => 'active',
        ]);
        $otherBranchProduct = Product::create([
            'name' => 'Other Branch Product',
            'item_id' => 'ROLE-RETURN-002',
            'store_hub_id' => $otherBranch->id,
            'stock' => 5,
            'status' => 'active',
        ]);
        $walkInSale = SalesTransaction::create([
            'store_hub_id' => $assignedBranch->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-10-01',
            'order_number' => 'PILOT-WALKIN-001',
            'customer_name' => 'Assigned Customer',
            'status' => 'confirmed',
        ]);
        $walkInItem = $walkInSale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
        ]);
        $onlineSale = SalesTransaction::create([
            'store_hub_id' => $assignedBranch->id,
            'channel_type' => 'online',
            'order_date' => '2026-10-01',
            'order_number' => 'PILOT-ONLINE-001',
            'customer_name' => 'Online Customer',
            'status' => 'confirmed',
        ]);
        $onlineItem = $onlineSale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $this->actingAs($salesAssociate)
            ->get(route('inventory-transactions.return.create', ['hub_id' => $assignedBranch->id]))
            ->assertOk();
        $this->get(route('inventory-transactions.return.create', ['hub_id' => $otherBranch->id]))
            ->assertForbidden();
        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $assignedBranch->id,
            'channel' => 'online',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $returnData = [
            'type' => 'return',
            'store_hub_id' => $assignedBranch->id,
            'occurred_on' => '2026-10-01',
            'channel' => 'online',
            'sales_transaction_id' => $onlineSale->id,
            'items' => [[
                'product_id' => $product->id,
                'transaction_item_id' => $onlineItem->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ];
        $this->post(route('inventory-transactions.return.store'), $returnData)
            ->assertForbidden();

        $walkInReturn = array_replace_recursive($returnData, [
            'channel' => 'walk_in',
            'sales_transaction_id' => $walkInSale->id,
            'items' => [[
                'transaction_item_id' => $walkInItem->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ]);
        $this->post(route('inventory-transactions.return.store'), array_replace($walkInReturn, [
            'store_hub_id' => $otherBranch->id,
            'items' => [[
                'product_id' => $otherBranchProduct->id,
                'transaction_item_id' => $walkInItem->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ]))->assertForbidden();

        $this->actingAs($inventoryStaff)
            ->get(route('inventory-transactions.return.create', ['hub_id' => $assignedBranch->id]))
            ->assertOk();
        $this->post(route('inventory-transactions.return.store'), array_replace($walkInReturn, [
            'store_hub_id' => $otherBranch->id,
            'items' => [[
                'product_id' => $otherBranchProduct->id,
                'transaction_item_id' => $walkInItem->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ]))->assertNotFound();

        $this->actingAs($salesMarketingStaff)
            ->get(route('inventory-transactions.return.create', ['hub_id' => $assignedBranch->id]))
            ->assertForbidden();
        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $assignedBranch->id,
            'channel' => 'online',
        ]))->assertForbidden();

        $this->assertSame(0, InventoryTransaction::where('type', 'return')->count());
    }
}
