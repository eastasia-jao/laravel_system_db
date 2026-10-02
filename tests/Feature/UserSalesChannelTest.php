<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSalesChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_fully_booked_channel_to_sales_marketing_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active']);

        $this->actingAs($admin)->post(route('users.store'), [
            'employee_id' => 'MKT-COVER',
            'first_name' => 'Marketing',
            'last_name' => 'Cover',
            'email' => 'marketing-cover@example.com',
            'mobile_number' => '09123456789',
            'hub_id' => $hub->id,
            'role' => 'sales_marketing_staff',
            'sales_channels' => ['shopee', 'online', 'fully_booked'],
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertSessionHasNoErrors()->assertRedirect()
            ->assertSessionHas('success', 'New staff account created successfully.');

        $staff = User::where('email', 'marketing-cover@example.com')->sole();
        $this->assertSame(['shopee', 'online', 'fully_booked'], $staff->sales_channels);
        $this->assertTrue($staff->canRecordChannelSales());
        $this->assertTrue($staff->hasSalesChannel('shopee'));
        $this->assertFalse($staff->hasSalesChannel('lazada'));
        $this->assertTrue($staff->hasSalesChannel('fully_booked'));
    }

    public function test_fully_booked_channel_cannot_be_assigned_to_inventory_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active']);

        $this->actingAs($admin)->from(route('users.index'))->post(route('users.store'), [
            'employee_id' => 'INV-FB',
            'first_name' => 'Inventory',
            'last_name' => 'Staff',
            'email' => 'inventory-fb@example.com',
            'mobile_number' => '09123456789',
            'hub_id' => $hub->id,
            'role' => 'inventory_staff',
            'sales_channels' => ['fully_booked'],
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertSessionHasErrors('sales_channels.0');

        $this->assertDatabaseMissing('users', ['employee_id' => 'INV-FB']);
    }

    public function test_employee_id_is_stored_in_uppercase_when_registering_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active']);

        $this->actingAs($admin)->post(route('users.store'), [
            'employee_id' => 'ac01',
            'first_name' => 'Jao',
            'last_name' => 'Lacatan',
            'mobile_number' => '09123456789',
            'hub_id' => $hub->id,
            'role' => 'admin',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $staff = User::where('employee_id', 'AC01')->sole();
        $this->assertSame('AC01', $staff->username);
    }

    public function test_edit_returns_a_success_message_for_the_popup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active']);
        $staff = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'role' => 'inventory_staff',
            'hub_id' => $hub->id,
        ]);

        $this->actingAs($admin)->putJson(route('users.update', $staff), [
            'name' => 'Updated Name',
            'employee_id' => 'upd-001',
            'email' => 'updated@example.com',
            'mobile_number' => '09123456789',
            'hub_id' => $hub->id,
            'role' => 'inventory_staff',
            'sales_channels' => ['online'],
        ])->assertOk()->assertJson([
            'success' => 'Staff account updated successfully.',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Updated Name',
            'employee_id' => 'UPD-001',
            'username' => 'UPD-001',
            'email' => 'updated@example.com',
        ]);
    }
}
