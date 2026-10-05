<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_account_is_locked_for_fifteen_minutes_after_five_failed_sign_in_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post('/login', [
                'username' => $user->username,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $lockoutMessage = 'This account has been temporarily locked after 5 unsuccessful sign-in attempts. Please contact the administrator.';

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['username' => $lockoutMessage]);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors(['username' => $lockoutMessage]);

        $this->assertGuest();
    }

    public function test_user_is_signed_out_after_fifteen_minutes_without_activity(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->withSession(['last_user_activity_at' => now()->subMinutes(16)->timestamp])
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'You were signed out after 15 minutes of inactivity. Please sign in again.');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
