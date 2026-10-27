<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\ActiveUserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

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
        $this->assertTrue(ActiveUserSession::hasActiveSession($user->id));
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_an_account_with_an_active_session_cannot_log_in_on_another_device(): void
    {
        $user = User::factory()->create();
        ActiveUserSession::claim($user->id, 'another-device-session');

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login')->assertSessionHasErrors([
            'username' => ActiveUserSession::ALREADY_LOGGED_IN_MESSAGE,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Account already logged in')
            ->assertSee(ActiveUserSession::ALREADY_LOGGED_IN_MESSAGE);
    }

    public function test_an_abandoned_session_stops_blocking_login_after_the_inactivity_window(): void
    {
        $user = User::factory()->create();
        ActiveUserSession::claim($user->id, 'abandoned-device-session');
        $this->travel(16)->minutes();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
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

    public function test_inactive_account_receives_a_clear_popup_message_when_credentials_are_correct(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $message = 'This account is inactive. Please contact the administrator to restore access.';

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login')->assertSessionHasErrors(['username' => $message]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Account inactive')
            ->assertSee($message);
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

    public function test_browser_inactivity_endpoint_returns_to_login_without_a_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->post(route('session.timeout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'You were signed out after 15 minutes of inactivity. Please sign in again.');

        $this->assertGuest();
        $this->assertFalse(ActiveUserSession::hasActiveSession($user->id));
    }

    public function test_an_expired_csrf_token_redirects_to_login_instead_of_rendering_419(): void
    {
        Route::middleware('web')->get('/test-expired-session', function () {
            throw new TokenMismatchException;
        });

        $this->get('/test-expired-session')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'Your session expired. Please sign in again.');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response = $this->post('/logout');

        $this->assertGuest();
        $this->assertFalse(ActiveUserSession::hasActiveSession($user->id));
        $response->assertRedirect(route('login'));
    }
}
