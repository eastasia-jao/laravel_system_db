<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StaffRole;
use App\Models\StoreHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuiltInRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_does_not_offer_custom_role_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('configuration'))
            ->assertOk()
            ->assertDontSee('data-tab-name="roles"', false)
            ->assertDontSee('Add Role with Selected Access');
    }

    public function test_staff_forms_offer_built_in_roles_when_role_records_are_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        StaffRole::query()->delete();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('value="admin"', false)
            ->assertSee('value="inventory_staff"', false)
            ->assertSee('value="sales_associate"', false)
            ->assertSee('value="sales_marketing_staff"', false)
            ->assertDontSee('value="audit"', false);
    }

    public function test_staff_creation_rejects_a_custom_role_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Test Hub', 'code' => 'TEST']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'employee_id' => 'EMP-AUDIT',
                'first_name' => 'Audit',
                'last_name' => 'User',
                'email' => 'audit@example.com',
                'mobile_number' => '09123456789',
                'hub_id' => $hub->id,
                'role' => 'audit',
                'password' => 'StrongPass1!',
                'password_confirmation' => 'StrongPass1!',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['employee_id' => 'EMP-AUDIT']);
    }

    public function test_staff_role_table_retains_only_the_four_built_in_roles(): void
    {
        $this->assertSame(
            array_keys(User::BUILT_IN_ROLES),
            StaffRole::where('is_system', true)->orderBy('slug')->pluck('slug')->all()
        );
        $this->assertSame(0, StaffRole::where('is_system', false)->count());
    }

    public function test_existing_custom_role_accounts_are_moved_to_sales_associate(): void
    {
        $user = User::factory()->create(['role' => 'audit']);
        DB::table('staff_roles')->insert([
            'name' => 'Audit',
            'slug' => 'audit',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_02_000001_remove_custom_staff_roles.php');
        $migration->up();

        $this->assertSame('sales_associate', $user->fresh()->role);
        $this->assertDatabaseMissing('staff_roles', ['slug' => 'audit']);
        $this->assertSame(4, StaffRole::where('is_system', true)->count());
        $this->assertFalse(Schema::hasColumn('staff_roles', 'permissions'));
    }
}
