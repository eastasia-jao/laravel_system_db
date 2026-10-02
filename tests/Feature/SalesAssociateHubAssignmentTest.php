<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAssociateHubAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_associate_can_be_assigned_multiple_hubs_and_access_is_limited_to_them(): void
    {
        $primaryHub = StoreHub::create(['name' => 'Primary Branch', 'code' => 'PRIMARY', 'status' => 'active', 'is_head_office' => false]);
        $additionalHub = StoreHub::create(['name' => 'Additional Branch', 'code' => 'ADDITIONAL', 'status' => 'active', 'is_head_office' => false]);
        $unassignedHub = StoreHub::create(['name' => 'Unassigned Branch', 'code' => 'UNASSIGNED', 'status' => 'active', 'is_head_office' => false]);
        $headOffice = StoreHub::create(['name' => 'Head Office', 'code' => 'HEAD-OFFICE', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Additional Assigned Branches')
            ->assertSee('id="edit_branch_search"', false)
            ->assertSee('class="edit-assignment-option"', false)
            ->assertDontSee('data-branch-name="'.$headOffice->name.'"', false)
            ->assertSee('data-head-office="true"', false)
            ->assertSee('function filterPrimaryHubOptions', false);
        $this->post(route('users.store'), [
            'employee_id' => 'ASSOC-001',
            'first_name' => 'Sales',
            'last_name' => 'Associate',
            'mobile_number' => '09123456789',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'sales_associate',
            'hub_id' => $primaryHub->id,
            'additional_hub_ids' => [$additionalHub->id],
        ])->assertSessionHasNoErrors();
        $this->post(route('users.store'), [
            'employee_id' => 'ASSOC-002',
            'first_name' => 'Invalid',
            'last_name' => 'Associate',
            'mobile_number' => '09123456788',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'sales_associate',
            'hub_id' => $primaryHub->id,
            'additional_hub_ids' => [$headOffice->id],
        ])->assertSessionHasErrors('additional_hub_ids.0');
        $this->post(route('users.store'), [
            'employee_id' => 'ASSOC-003',
            'first_name' => 'Head',
            'last_name' => 'Office',
            'mobile_number' => '09123456787',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'sales_associate',
            'hub_id' => $headOffice->id,
        ])->assertSessionHasErrors('hub_id');

        $associate = User::where('employee_id', 'ASSOC-001')->sole();
        $this->get(route('users.edit', $associate->id))
            ->assertOk()
            ->assertJsonPath('user.additional_hub_ids.0', $additionalHub->id);
        $this->assertEqualsCanonicalizing(
            [$primaryHub->id, $additionalHub->id],
            $associate->accessibleStoreHubIds()
        );

        $this->actingAs($associate)
            ->get(route('hub.dashboard', $additionalHub->id))
            ->assertOk()
            ->assertSee('name="sales_channel" value="walk_in"', false);
        $this->get(route('hub.dashboard', $unassignedHub->id))->assertForbidden();
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertEqualsCanonicalizing(
            [$primaryHub->id, $additionalHub->id],
            $dashboard->viewData('dashboardHubs')->modelKeys()
        );
        $this->get(route('inventory-transactions.return.create', ['hub_id' => $additionalHub->id]))
            ->assertOk()
            ->assertSee('name="store_hub_id"', false)
            ->assertSee('value="'.$additionalHub->id.'"', false)
            ->assertSee('<option value="walk_in" selected>Walk-In</option>', false)
            ->assertDontSee('>'.$unassignedHub->name.'</option>', false)
            ->assertDontSee('name="store_hub_id" class="form-select" required disabled', false);
        $this->get(route('inventory-transactions.return.create', ['hub_id' => $unassignedHub->id]))
            ->assertForbidden();

        $this->actingAs($admin)->put(route('users.update', $associate->id), [
            'name' => 'Sales Associate',
            'mobile_number' => '09123456789',
            'role' => 'sales_associate',
            'hub_id' => $additionalHub->id,
            'additional_hub_ids' => [$primaryHub->id],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$primaryHub->id, $additionalHub->id],
            $associate->fresh()->accessibleStoreHubIds()
        );

        $this->put(route('users.update', $associate->id), [
            'name' => 'Sales Associate',
            'mobile_number' => '09123456789',
            'role' => 'sales_associate',
            'hub_id' => $headOffice->id,
        ])->assertSessionHasErrors('hub_id');
    }

    public function test_single_assigned_branch_locks_return_store_selection(): void
    {
        $branch = StoreHub::create(['name' => 'Assigned Branch', 'code' => 'ASSIGNED', 'status' => 'active']);
        $unassignedBranch = StoreHub::create(['name' => 'Other Branch', 'code' => 'OTHER', 'status' => 'active']);
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $branch->id]);

        $this->actingAs($associate)
            ->get(route('inventory-transactions.return.create'))
            ->assertOk()
            ->assertSee('name="store_hub_id" class="form-select" required disabled', false)
            ->assertSee('name="store_hub_id" value="'.$branch->id.'"', false)
            ->assertDontSee('>'.$unassignedBranch->name.'</option>', false);
    }

    public function test_sales_associate_can_select_products_from_any_assigned_branch(): void
    {
        $primaryHub = StoreHub::create(['name' => 'Primary Branch', 'code' => 'PRODUCT-PRIMARY', 'status' => 'active']);
        $additionalHub = StoreHub::create(['name' => 'Additional Branch', 'code' => 'PRODUCT-ADDITIONAL', 'status' => 'active']);
        $unassignedHub = StoreHub::create(['name' => 'Unassigned Branch', 'code' => 'PRODUCT-OTHER', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $primaryHub->id]);
        $staff->assignedStoreHubs()->attach($additionalHub->id);
        Product::create(['store_hub_id' => $primaryHub->id, 'item_id' => 'PRIMARY-ITEM', 'name' => 'Primary branch product']);
        Product::create(['store_hub_id' => $additionalHub->id, 'item_id' => 'ADDITIONAL-ITEM', 'name' => 'Additional branch product']);
        Product::create(['store_hub_id' => $unassignedHub->id, 'item_id' => 'PRIVATE-ITEM', 'name' => 'Unassigned branch product']);

        $primaryResponse = $this->actingAs($staff)->get(route('products.index'));
        $primaryResponse->assertOk()
            ->assertSee('id="storeHubSelect"', false)
            ->assertSee('Primary branch product')
            ->assertDontSee('Additional branch product')
            ->assertDontSee('Unassigned branch product')
            ->assertViewHas('selectedHub', fn ($hub) => $hub->is($primaryHub));

        $additionalResponse = $this->get(route('products.index', ['hub_id' => $additionalHub->id]));
        $additionalResponse->assertOk()
            ->assertSee('Additional branch product')
            ->assertDontSee('Primary branch product')
            ->assertDontSee('Unassigned branch product')
            ->assertViewHas('selectedHub', fn ($hub) => $hub->is($additionalHub));

        $this->get(route('products.index', ['hub_id' => $unassignedHub->id]))
            ->assertOk()
            ->assertSee('Primary branch product')
            ->assertDontSee('Unassigned branch product');
    }

    public function test_return_channel_is_locked_to_walk_in_for_non_head_office_hubs(): void
    {
        $branch = StoreHub::create([
            'name' => 'Branch Store',
            'code' => 'BRANCH-STORE',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $headOffice = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HEAD-OFFICE',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.return.create', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertSee('name="channel" id="returnChannel" class="form-select" required disabled', false)
            ->assertSee('name="channel" value="walk_in"', false)
            ->assertSee('<option value="walk_in" selected>Walk-In</option>', false);

        $this->get(route('inventory-transactions.return.create', ['hub_id' => $headOffice->id]))
            ->assertOk()
            ->assertSee('name="channel" id="returnChannel" class="form-select" required', false)
            ->assertDontSee('name="channel" id="returnChannel" class="form-select" required disabled', false)
            ->assertDontSee('name="channel" value="walk_in"', false);
    }

    public function test_walk_in_order_number_preview_uses_walk_in_reference_format(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BRANCH', 'status' => 'active']);
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);

        $this->actingAs($associate)
            ->getJson(route('sales.order-number-preview', ['channel' => 'walk_in']))
            ->assertOk()
            ->assertJsonPath('order_number', 'WALK-IN001');
    }
}
