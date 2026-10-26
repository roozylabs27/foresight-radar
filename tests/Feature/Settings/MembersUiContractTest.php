<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for UX-001: the Members page gets the identifier its member
 * routes bind by, and redirect messages reach the page as shared flash props.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class MembersUiContractTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'manage-members', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'organization', 'description' => 'manage-members']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin'])
            ->givePermissionTo('manage-members');
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Board of Directors']);

        $tenant = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active', 'max_users' => 15]);
        $this->admin = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Admin']);
        $this->admin->assignRole('admin');
        $this->member = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Member']);
        $this->member->assignRole('bod');
    }

    public function test_member_rows_carry_the_uuid_that_member_routes_bind_by(): void
    {
        $uuid = null;
        $this->actingAs($this->admin)
            ->get(route('settings.members.index'))
            ->assertInertia(function ($page) use (&$uuid) {
                $row = collect($page->toArray()['props']['members']['data'])->firstWhere('name', 'Bob Member');
                $uuid = $row['uuid'];

                return $page;
            });

        $this->assertSame($this->member->uuid, $uuid);
        $this->actingAs($this->admin)
            ->patch("/settings/members/{$uuid}/role", ['role' => 'admin'])
            ->assertRedirect();
        $this->assertTrue($this->member->fresh()->hasRole('admin'));
    }

    public function test_success_message_reaches_the_page_as_flash(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->from(route('settings.members.index'))
            ->followingRedirects()
            ->post(route('settings.members.invite'), ['email' => 'new@acme.test', 'name' => 'New', 'role' => 'bod'])
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Members')
                ->where('flash.success', fn ($message) => str_contains((string) $message, 'new@acme.test'))
                ->where('flash.error', null));
    }

    public function test_error_message_reaches_the_page_as_flash(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.members.index'))
            ->followingRedirects()
            ->delete(route('settings.members.destroy', ['user' => $this->admin]))
            ->assertInertia(fn ($page) => $page
                ->where('flash.error', fn ($message) => str_contains((string) $message, 'tidak dapat menghapus akun Anda sendiri')));
    }
}
