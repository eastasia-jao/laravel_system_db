<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_staff_must_replace_the_admin_issued_password_before_using_the_system(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create([
            'name' => 'Password Change Branch',
            'code' => 'PASSWORD-CHANGE',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'employee_id' => 'AC123',
                'first_name' => 'New',
                'last_name' => 'Staff',
                'email' => 'new.staff@example.com',
                'mobile_number' => '09123456789',
                'hub_id' => $hub->id,
                'role' => 'sales_marketing_staff',
                'sales_channels' => [],
                'password' => 'TemporaryPass1!',
                'password_confirmation' => 'TemporaryPass1!',
            ])
            ->assertSessionHasNoErrors();

        $staff = User::where('employee_id', 'AC123')->firstOrFail();
        $this->assertTrue($staff->must_change_password);

        $this->post(route('logout'));

        $this->post(route('login'), [
            'username' => 'AC123',
            'password' => 'TemporaryPass1!',
        ])->assertRedirect(route('password.change-required'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('password.change-required'));

        $this->get(route('password.change-required'))
            ->assertOk()
            ->assertSee('Create your private password')
            ->assertSee('id="passwordStrengthMeter"', false)
            ->assertSee('id="passwordRequirementsDisclosure"', false)
            ->assertSee('id="passwordRequirementsCount"', false)
            ->assertSee('0/6 met')
            ->assertSee('At least 12 characters')
            ->assertSee('One uppercase letter')
            ->assertSee('One lowercase letter')
            ->assertSee('One number')
            ->assertSee('One symbol')
            ->assertSee('Different from temporary password')
            ->assertSee("window.AppAlert.show(message, 'error'", false);

        $this->put(route('password.update'), [
            'current_password' => 'TemporaryPass1!',
            'password' => 'TemporaryPass1!',
            'password_confirmation' => 'TemporaryPass1!',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue($staff->fresh()->must_change_password);

        $this->put(route('password.update'), [
            'current_password' => 'TemporaryPass1!',
            'password' => 'PrivatePassword2!',
            'password_confirmation' => 'PrivatePassword2!',
        ])->assertRedirect(route('dashboard'));

        $staff->refresh();
        $this->assertFalse($staff->must_change_password);
        $this->assertTrue(Hash::check('PrivatePassword2!', $staff->password));
        $this->assertFalse(Hash::check('TemporaryPass1!', $staff->password));
    }

    public function test_required_password_change_enforces_every_strong_password_requirement(): void
    {
        $staff = User::factory()->create([
            'password' => 'TemporaryPass1!',
            'must_change_password' => true,
        ]);
        $this->actingAs($staff);

        foreach ([
            'Short1!' => 'minimum length',
            'lowercasepassword1!' => 'uppercase letter',
            'UPPERCASEPASSWORD1!' => 'lowercase letter',
            'PasswordWithoutNumber!' => 'number',
            'PasswordWithoutSymbol1' => 'symbol',
        ] as $candidate => $requirement) {
            $this->put(route('password.update'), [
                'current_password' => 'TemporaryPass1!',
                'password' => $candidate,
                'password_confirmation' => $candidate,
            ])->assertSessionHasErrorsIn('updatePassword', 'password');

            $this->assertTrue($staff->fresh()->must_change_password, "The {$requirement} requirement should keep the forced change active.");
            $this->assertTrue(Hash::check('TemporaryPass1!', $staff->password), "The {$requirement} requirement should reject the candidate password.");
        }
    }

    public function test_sales_marketing_staff_without_an_assigned_channel_cannot_open_record_sale(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HEAD-OFFICE-NO-CHANNEL',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'sales_marketing_staff',
            'sales_channels' => [],
        ]);

        $this->actingAs($staff)
            ->get(route('hub.dashboard', $hub))
            ->assertOk()
            ->assertDontSee('Record Multi-Channel Sale');

        $this->post(route('sales.storeMultiChannelSale'))
            ->assertForbidden();
    }

    public function test_fully_booked_only_staff_do_not_see_multi_channel_sales_operations(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HEAD-OFFICE-FULLY-BOOKED',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'sales_marketing_staff',
            'sales_channels' => ['fully_booked'],
        ]);

        $this->assertFalse($staff->canRecordChannelSales());

        $this->actingAs($staff)
            ->get(route('hub.dashboard', $hub))
            ->assertOk()
            ->assertDontSee('Sales operations')
            ->assertDontSee('Record Multi-Channel Sale')
            ->assertDontSee('Rejected Sales')
            ->assertSee('Fully Booked operations')
            ->assertSee('Fully Booked Order');

        $this->get(route('sales.rejected'))
            ->assertForbidden();

        $this->post(route('sales.storeMultiChannelSale'))
            ->assertForbidden();
    }
}
