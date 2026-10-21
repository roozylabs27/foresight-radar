<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered_when_enabled(): void
    {
        config(['auth.allow_registration' => true]);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_is_forbidden_when_disabled(): void
    {
        config(['auth.allow_registration' => false]);

        $response = $this->get('/register');

        $response->assertStatus(403);
    }

    public function test_new_users_can_register_when_enabled(): void
    {
        config(['auth.allow_registration' => true]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_new_users_cannot_register_when_disabled(): void
    {
        config(['auth.allow_registration' => false]);

        $response = $this->post('/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(403);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_inertia_shares_can_register_flag(): void
    {
        config(['auth.allow_registration' => false]);
        $response = $this->get('/login');
        $response->assertInertia(fn ($page) => $page->where('canRegister', false));

        config(['auth.allow_registration' => true]);
        $response = $this->get('/login');
        $response->assertInertia(fn ($page) => $page->where('canRegister', true));
    }
}
