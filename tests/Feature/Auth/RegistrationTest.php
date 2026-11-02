<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for TENANT-010: accounts are invitation-only. There is no
 * self-registration route that could create a tenant-less account.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_endpoint_does_not_create_accounts(): void
    {
        $this->post('/register', [
            'name' => 'Anonymous User',
            'email' => 'anon@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'anon@example.com']);
    }

    public function test_inertia_no_longer_shares_a_registration_flag(): void
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->missing('canRegister'));
    }
}
