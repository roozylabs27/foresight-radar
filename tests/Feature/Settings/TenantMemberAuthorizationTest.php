<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for INVITE-001 (member and organization management needs
 * admin permissions), INVITE-002 (invite never takes over an existing
 * account) and INVITE-006 (a tenant always keeps at least one admin).
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class TenantMemberAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $bod;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['manage-members', 'manage-organization'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], [
                'uuid' => (string) Uuid::uuid4(),
                'category' => 'organization',
                'description' => $name,
            ]);
        }
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin']);
        $adminRole->givePermissionTo(['manage-members', 'manage-organization']);
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Board of Directors']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Super Admin']);

        $this->tenant = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active', 'max_users' => 15]);

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'alice@acme.test']);
        $this->admin->assignRole('admin');
        $this->bod = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'bob@acme.test']);
        $this->bod->assignRole('bod');
    }

    public function test_bod_member_can_view_members_and_organization_pages(): void
    {
        $this->actingAs($this->bod)->get(route('settings.members.index'))->assertOk();
        $this->actingAs($this->bod)->get(route('settings.organization.index'))->assertOk();
    }

    public function test_bod_member_cannot_invite_members(): void
    {
        $this->actingAs($this->bod)
            ->post(route('settings.members.invite'), ['email' => 'mallory@evil.test', 'name' => 'Mallory', 'role' => 'admin'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'mallory@evil.test']);
        $this->assertDatabaseMissing('tenant_invitations', ['email' => 'mallory@evil.test']);
    }

    public function test_bod_member_cannot_promote_self_to_admin(): void
    {
        $this->actingAs($this->bod)
            ->patch(route('settings.members.role', ['user' => $this->bod]), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertFalse($this->bod->fresh()->hasRole('admin'));
    }

    public function test_bod_member_cannot_remove_admin(): void
    {
        $this->actingAs($this->bod)
            ->delete(route('settings.members.destroy', ['user' => $this->admin]))
            ->assertForbidden();

        $this->assertSame($this->tenant->id, (int) $this->admin->fresh()->tenant_id);
    }

    public function test_bod_member_cannot_cancel_invitation(): void
    {
        $invitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'pending@acme.test',
            'role' => 'bod',
            'token' => str_repeat('t', 40),
            'expires_at' => now()->addDay(),
        ]);
        app(TenantContext::class)->reset();

        $this->actingAs($this->bod)
            ->delete(route('settings.members.invitations.cancel', ['invitation' => $invitation]))
            ->assertForbidden();

        $this->assertDatabaseHas('tenant_invitations', ['id' => $invitation->id]);
    }

    public function test_bod_member_cannot_update_organization_profile(): void
    {
        $this->actingAs($this->bod)
            ->patch(route('settings.organization.update'), ['name' => 'Pwned', 'settings' => ['x' => 1]])
            ->assertForbidden();

        $this->assertSame('Acme Corporation', $this->tenant->fresh()->name);
    }

    public function test_demoted_admin_loses_directly_granted_permissions(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $other->assignRole('admin');
        $other->givePermissionTo('manage-members');

        $this->actingAs($this->admin)
            ->patch(route('settings.members.role', ['user' => $other]), ['role' => 'bod'])
            ->assertRedirect();

        $this->assertFalse($other->fresh()->can('manage-members'));
    }

    public function test_last_admin_cannot_demote_self(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('settings.members.role', ['user' => $this->admin]), ['role' => 'bod'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_admin_can_demote_peer_while_another_admin_remains(): void
    {
        $peer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $peer->assignRole('admin');

        $this->actingAs($this->admin)
            ->patch(route('settings.members.role', ['user' => $peer]), ['role' => 'bod'])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertTrue($peer->fresh()->hasRole('bod'));
        $this->assertFalse($peer->fresh()->hasRole('admin'));
    }

    public function test_admin_can_demote_self_while_another_admin_remains(): void
    {
        $peer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $peer->assignRole('admin');

        $this->actingAs($this->admin)
            ->patch(route('settings.members.role', ['user' => $this->admin]), ['role' => 'bod'])
            ->assertSessionMissing('error');

        $this->assertTrue($this->admin->fresh()->hasRole('bod'));
    }

    public function test_last_admin_cannot_be_removed(): void
    {
        Role::findByName('super-admin')->givePermissionTo('manage-members');
        $operator = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $operator->assignRole('super-admin');

        $this->actingAs($operator)
            ->delete(route('settings.members.destroy', ['user' => $this->admin]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame($this->tenant->id, (int) $this->admin->fresh()->tenant_id);
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_admin_can_remove_peer_admin_while_another_admin_remains(): void
    {
        $peer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $peer->assignRole('admin');

        $this->actingAs($this->admin)
            ->delete(route('settings.members.destroy', ['user' => $peer]))
            ->assertSessionMissing('error');

        $this->assertNull($peer->fresh()->tenant_id);
    }

    /** TENANT-009: another tenant's member looks exactly like an unknown uuid. */
    public function test_foreign_member_is_indistinguishable_from_unknown_uuid(): void
    {
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        $foreign = User::factory()->create(['tenant_id' => $other->id]);
        $foreign->assignRole('admin');

        $this->actingAs($this->admin)
            ->patch(route('settings.members.role', ['user' => $foreign]), ['role' => 'bod'])
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->delete(route('settings.members.destroy', ['user' => $foreign]))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->patch(route('settings.members.role', ['user' => (string) Uuid::uuid4()]), ['role' => 'bod'])
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->hasRole('admin'));
        $this->assertSame($other->id, (int) $foreign->fresh()->tenant_id);
    }

    public function test_invite_does_not_absorb_platform_superuser(): void
    {
        $platform = User::factory()->create(['tenant_id' => null, 'email' => 'root@platform.test']);
        $platform->assignRole('super-admin');

        // INVITE-008: same response as a new email; the account itself is never touched.
        $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'root@platform.test', 'name' => 'Root', 'role' => 'bod'])
            ->assertSessionHasNoErrors();

        $platform->refresh();
        $this->assertNull($platform->tenant_id);
        $this->assertTrue($platform->hasRole('super-admin'));
        $this->assertFalse($platform->hasRole('bod'));
    }

    public function test_invite_does_not_attach_unassigned_user(): void
    {
        $orphan = User::factory()->create(['tenant_id' => null, 'email' => 'former@member.test']);

        $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'former@member.test', 'name' => 'Former', 'role' => 'admin'])
            ->assertSessionHasNoErrors();

        $this->assertNull($orphan->fresh()->tenant_id);
        $this->assertFalse($orphan->fresh()->hasRole('admin'));
    }

    public function test_invite_for_soft_deleted_account_leaves_the_account_untouched(): void
    {
        $ghost = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'ghost@acme.test']);
        $ghost->delete();

        $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'ghost@acme.test', 'name' => 'Ghost', 'role' => 'bod'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($ghost->fresh()->trashed());
        $this->assertSame(1, User::withTrashed()->where('email', 'ghost@acme.test')->count());
    }

    /** INVITE-008: an email registered elsewhere gets the same answer as a brand-new one. */
    public function test_invite_response_does_not_reveal_registered_emails(): void
    {
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        User::factory()->create(['tenant_id' => $other->id, 'email' => 'taken@cyberdyne.test']);

        $taken = $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'taken@cyberdyne.test', 'role' => 'bod']);
        $taken->assertSessionHasNoErrors();
        $takenMessage = str_replace('taken@cyberdyne.test', '{email}', (string) session('success'));

        $fresh = $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'nobody@new.test', 'role' => 'bod']);
        $fresh->assertSessionHasNoErrors();
        $freshMessage = str_replace('nobody@new.test', '{email}', (string) session('success'));

        $this->assertSame($fresh->getStatusCode(), $taken->getStatusCode());
        $this->assertNotSame('', $freshMessage);
        $this->assertSame($freshMessage, $takenMessage);
        $this->assertSame($other->id, (int) User::where('email', 'taken@cyberdyne.test')->value('tenant_id'));
    }

    /** INVITE-008: invites are rate limited per inviter. */
    public function test_invites_are_throttled_per_inviter(): void
    {
        foreach (range(1, 10) as $i) {
            $this->actingAs($this->admin)
                ->post(route('settings.members.invite'), ['email' => "bulk{$i}@acme.test", 'role' => 'bod'])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)
            ->post(route('settings.members.invite'), ['email' => 'bulk11@acme.test', 'role' => 'bod'])
            ->assertStatus(429);
        $this->assertDatabaseMissing('tenant_invitations', ['email' => 'bulk11@acme.test']);
    }
}
