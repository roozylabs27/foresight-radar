<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Providers\RouteServiceProvider;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for INVITE-003/004: invitations stay pending until the
 * recipient accepts through a signed, single-use, expiring link.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class TenantInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'manage-members', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'organization', 'description' => 'manage-members']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin'])
            ->givePermissionTo('manage-members');
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Board of Directors']);

        $this->tenant = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active', 'max_users' => 15]);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        Notification::fake();
    }

    public function test_invite_creates_pending_invitation_and_queues_email_without_creating_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'New@Acme.test ', 'name' => 'New Hire', 'role' => 'bod'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['email' => 'new@acme.test']);
        $invitation = $this->invitationFor('new@acme.test');
        $this->assertNull($invitation->accepted_at);
        $this->assertSame('bod', $invitation->role);

        $notification = $this->sentNotification('new@acme.test');
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(hash('sha256', $this->tokenFrom($notification->acceptUrl)), $invitation->token);

        $message = (string) $response->getSession()->get('success');
        $this->assertStringNotContainsString('Kata sandi', $message);
        $this->assertStringContainsString('dijadwalkan', $message);
    }

    public function test_reinviting_pending_email_rotates_token_and_resends(): void
    {
        $this->invite('again@acme.test');
        $firstHash = $this->invitationFor('again@acme.test')->token;

        $this->invite('again@acme.test', 'admin');

        $invitation = $this->invitationFor('again@acme.test');
        $this->assertNotSame($firstHash, $invitation->token);
        $this->assertSame('admin', $invitation->role);
        $this->assertSame(1, TenantInvitation::withoutGlobalScope(TenantScope::class)->where('email', 'again@acme.test')->count());
        Notification::assertSentOnDemandTimes(TenantInvitationNotification::class, 2);
    }

    public function test_invite_without_optional_name_succeeds(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'noname@acme.test', 'role' => 'bod'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->invitationFor('noname@acme.test'));
    }

    public function test_guest_sees_accept_page_for_valid_link(): void
    {
        $url = $this->invite('guest@acme.test');

        $this->get($url)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn ($page) => $page
                ->component('Auth/AcceptInvitation')
                ->where('state', 'valid')
                ->where('invitation.email', 'guest@acme.test')
                ->where('invitation.organization', 'Acme Corporation'));
    }

    /** E2E-001: nginx appends its own Referrer-Policy, so the document itself must forbid referrers. */
    public function test_accept_page_document_forbids_referrers_before_any_subresource(): void
    {
        $url = $this->invite('meta@acme.test');

        $html = $this->get($url)->assertOk()->getContent();
        $meta = strpos($html, '<meta name="referrer" content="no-referrer">');
        $this->assertNotFalse($meta);
        $this->assertLessThan(strpos($html, 'fonts.googleapis.com'), $meta);

        $this->assertStringNotContainsString('name="referrer"', $this->get(route('login'))->getContent());
    }

    /** E2E-003: the page names the role the way the email does. */
    public function test_accept_page_shows_role_display_name(): void
    {
        $url = $this->invite('role@acme.test');

        $this->get($url)->assertInertia(fn ($page) => $page->where('invitation.role', 'Board of Directors'));
    }

    public function test_accepting_creates_verified_member_with_invited_role_and_logs_in(): void
    {
        $url = $this->invite('joiner@acme.test', 'admin');

        $this->post($url, ['name' => 'Joiner', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])
            ->assertRedirect(RouteServiceProvider::HOME);

        $user = User::where('email', 'joiner@acme.test')->firstOrFail();
        $this->assertSame($this->tenant->id, (int) $user->tenant_id);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($this->invitationFor('joiner@acme.test')->accepted_at);
        $this->assertSame($user->id, Auth::id());
    }

    public function test_link_cannot_be_reused_after_acceptance(): void
    {
        $url = $this->invite('once@acme.test');
        $payload = ['name' => 'Once', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'];
        $this->post($url, $payload);
        Auth::logout();

        $this->post($url, $payload)->assertStatus(410);
        $this->get($url)->assertStatus(410)->assertInertia(fn ($page) => $page->where('state', 'accepted'));
        $this->assertSame(1, User::where('email', 'once@acme.test')->count());
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $url = $this->invite('late@acme.test');
        $this->travel(8)->days();

        $this->get($url)->assertStatus(410)->assertInertia(fn ($page) => $page->where('state', 'expired'));
        $this->post($url, ['name' => 'Late', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])
            ->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'late@acme.test']);
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $url = $this->invite('revoked@acme.test');
        $invitation = $this->invitationFor('revoked@acme.test');
        app(TenantContext::class)->reset();
        $this->actingAs($this->admin)->delete(route('settings.members.invitations.cancel', ['invitation' => $invitation->id]));
        Auth::logout();
        app(TenantContext::class)->reset();

        $this->post($url, ['name' => 'Revoked', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])
            ->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'revoked@acme.test']);
    }

    public function test_tampered_or_unsigned_link_is_rejected(): void
    {
        $url = $this->invite('tamper@acme.test');

        $this->get(str_replace('token=', 'token=x', $url))->assertForbidden();
        $this->get(route('invitations.accept', ['invitation' => $this->invitationFor('tamper@acme.test')->id, 'token' => 'guess']))->assertForbidden();
    }

    public function test_validly_signed_link_with_wrong_token_is_invalid(): void
    {
        $this->invite('wrong@acme.test');
        $url = URL::signedRoute('invitations.accept', ['invitation' => $this->invitationFor('wrong@acme.test')->id, 'token' => str_repeat('a', 64)]);

        $this->get($url)->assertStatus(410)->assertInertia(fn ($page) => $page->where('state', 'invalid'));
        $this->post($url, ['name' => 'Wrong', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'wrong@acme.test']);
    }

    public function test_signed_in_user_cannot_accept_someone_elses_invitation(): void
    {
        $url = $this->invite('target@acme.test');
        $other = User::factory()->create(['tenant_id' => null]);
        app(TenantContext::class)->reset();

        $this->actingAs($other)
            ->post($url, ['name' => 'Hijack', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])
            ->assertForbidden();

        $this->assertNull($other->fresh()->tenant_id);
        $this->assertDatabaseMissing('users', ['email' => 'target@acme.test']);
        $this->assertNull($this->invitationFor('target@acme.test')->accepted_at);
    }

    public function test_suspended_tenant_invitation_cannot_be_accepted(): void
    {
        $url = $this->invite('frozen@acme.test');
        $this->tenant->update(['status' => 'suspended']);

        $this->post($url, ['name' => 'Frozen', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])
            ->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'frozen@acme.test']);
    }

    public function test_account_created_meanwhile_blocks_acceptance_without_consuming_invitation(): void
    {
        $url = $this->invite('race@acme.test');
        User::factory()->create(['tenant_id' => null, 'email' => 'race@acme.test']);

        $this->post($url, ['name' => 'Race', 'password' => 'Str0ng-pass!', 'password_confirmation' => 'Str0ng-pass!'])
            ->assertSessionHasErrors('email');

        $this->assertNull($this->invitationFor('race@acme.test')->accepted_at);
        $this->assertSame(1, User::where('email', 'race@acme.test')->count());
    }

    private function invite(string $email, string $role = 'bod'): string
    {
        $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => $email, 'name' => 'Invitee', 'role' => $role])
            ->assertSessionHasNoErrors();
        Auth::logout();
        app(TenantContext::class)->reset();

        return $this->sentNotification($email)->acceptUrl;
    }

    private function sentNotification(string $email): TenantInvitationNotification
    {
        $sent = null;
        Notification::assertSentOnDemand(TenantInvitationNotification::class, function ($notification, $channels, $notifiable) use ($email, &$sent) {
            if ($notifiable->routes['mail'] === $email) {
                $sent = $notification;
            }

            return $notifiable->routes['mail'] === $email;
        });

        return $sent;
    }

    private function invitationFor(string $email): ?TenantInvitation
    {
        return TenantInvitation::withoutGlobalScope(TenantScope::class)->where('email', $email)->first();
    }

    private function tokenFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['token'];
    }
}
