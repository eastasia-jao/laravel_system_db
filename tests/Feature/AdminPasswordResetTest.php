<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reset_a_staff_password_without_exposing_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'password' => 'OriginalPass1!']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertSee('name="password_confirmation"', false);

        $response = $this->postJson(route('users.reset-password', $staff), [
            'password' => 'NewSecurePass2!',
            'password_confirmation' => 'NewSecurePass2!',
        ]);

        $response->assertOk()
            ->assertJson(['success' => 'Password reset successfully.'])
            ->assertDontSee('NewSecurePass2!');
        $this->assertTrue(Hash::check('NewSecurePass2!', $staff->fresh()->password));
        $this->assertFalse(Hash::check('OriginalPass1!', $staff->fresh()->password));

        $this->getJson(route('users.edit', $staff))->assertJsonMissingPath('user.password');
    }

    public function test_password_reset_requires_a_strong_confirmed_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'password' => 'OriginalPass1!']);

        $this->actingAs($admin)
            ->postJson(route('users.reset-password', $staff), [
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('OriginalPass1!', $staff->fresh()->password));
    }

    public function test_only_admins_can_reset_staff_passwords(): void
    {
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $otherStaff = User::factory()->create(['role' => 'sales_associate']);

        $this->actingAs($staff)
            ->postJson(route('users.reset-password', $otherStaff), [
                'password' => 'NewSecurePass2!',
                'password_confirmation' => 'NewSecurePass2!',
            ])
            ->assertForbidden();

        $this->assertFalse(Hash::check('NewSecurePass2!', $otherStaff->fresh()->password));
    }
}
