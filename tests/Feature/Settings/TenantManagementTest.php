<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Admin',
        ]);
        $bodRole = Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Board of Directors',
        ]);
        foreach (['manage-members', 'manage-organization'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], [
                'uuid' => (string) Uuid::uuid4(),
                'category' => 'organization',
                'description' => $permission,
            ]);
        }
        $adminRole->givePermissionTo(['manage-members', 'manage-organization']);

        $this->tenant = Tenant::create([
            'id' => 10,
            'uuid' => (string) Uuid::uuid4(),
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'status' => 'active',
            'max_users' => 15,
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alice Admin',
            'email' => 'alice@acme.com',
        ]);
        $this->adminUser->assignRole($adminRole);

        $this->memberUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bob Member',
            'email' => 'bob@acme.com',
        ]);
        $this->memberUser->assignRole($bodRole);
    }

    public function test_user_can_view_organization_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('settings.organization.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Organization')
            ->where('organization.name', 'Acme Corporation')
            ->has('stats.members_count')
        );
    }

    public function test_user_can_update_organization_profile(): void
    {
        $response = $this->actingAs($this->adminUser)->patch(route('settings.organization.update'), [
            'name' => 'Acme Global Holdings',
            'domain' => 'acmeglobal.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'name' => 'Acme Global Holdings',
            'domain' => 'acmeglobal.com',
        ]);
    }

    public function test_user_can_view_tenant_members_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('settings.members.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Members')
            ->has('members.data', 2)
            ->where('members.data.0.email', 'alice@acme.com')
            ->where('members.current_page', 1)
        );
    }

    public function test_user_can_filter_members_by_search_keyword(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('settings.members.index', ['search' => 'Alice']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Members')
            ->has('members.data', 1)
            ->where('members.data.0.name', 'Alice Admin')
        );
    }

    public function test_admin_can_invite_new_member_as_pending_invitation(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->adminUser)->post(route('settings.members.invite'), [
            'email' => 'charlie@acme.com',
            'name' => 'Charlie Analyst',
            'role' => 'bod',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // The account is created only when the recipient accepts (TenantInvitationLifecycleTest).
        $this->assertDatabaseMissing('users', ['email' => 'charlie@acme.com']);
        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $this->tenant->id,
            'email' => 'charlie@acme.com',
            'role' => 'bod',
            'accepted_at' => null,
        ]);
        Notification::assertSentOnDemand(TenantInvitationNotification::class);
    }

    public function test_inviting_member_belonging_to_another_tenant_is_rejected(): void
    {
        $otherTenant = Tenant::create([
            'id' => 99,
            'uuid' => (string) Uuid::uuid4(),
            'name' => 'Other Corp',
            'slug' => 'other-corp',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'other@othercorp.com',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('settings.members.invite'), [
            'email' => 'other@othercorp.com',
            'role' => 'bod',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_member_role(): void
    {
        $response = $this->actingAs($this->adminUser)->patch(
            route('settings.members.role', ['user' => $this->memberUser]),
            ['role' => 'admin']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->memberUser->refresh();
        $this->assertTrue($this->memberUser->hasRole('admin'));
    }

    public function test_admin_can_remove_member_from_tenant(): void
    {
        $response = $this->actingAs($this->adminUser)->delete(
            route('settings.members.destroy', ['user' => $this->memberUser])
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->memberUser->refresh();
        $this->assertNull($this->memberUser->tenant_id);
    }

    public function test_user_cannot_remove_themselves_from_tenant(): void
    {
        $response = $this->actingAs($this->adminUser)->delete(
            route('settings.members.destroy', ['user' => $this->adminUser])
        );

        $response->assertSessionHas('error');
        $this->adminUser->refresh();
        $this->assertEquals($this->tenant->id, $this->adminUser->tenant_id);
    }
}
