<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Audit AUD-14: User Management is the platform operators' tool (only `developer` and
 * `super-admin` hold the user permissions; organisations invite their own members in Settings).
 * A user created there belongs to the organisation the creator works in: their own, or the
 * workspace an operator picked. Without one, nothing is created (a user without an organisation
 * could not sign in).
 */
class UserManagementTenantTest extends TestCase
{
    use RefreshDatabase;

    private Role $staff;

    private Tenant $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-user', 'create-user'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'user', 'description' => $name]);
        }
        foreach (['developer', 'super-admin', 'staff'] as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => ucfirst($name)]);
            if ($name !== 'staff') {
                $role->givePermissionTo(['view-user', 'create-user']);
            }
        }
        $this->staff = Role::where('name', 'staff')->sole();
        $this->workspace = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
    }

    private function create(User $creator, string $email)
    {
        return $this->actingAs($creator)->postJson('/user-management/user', [
            'type' => 'create', 'name' => 'Analis Baru', 'email' => $email, 'password' => 'Password123!', 'role_id' => $this->staff->id,
        ]);
    }

    public function test_a_user_created_by_an_organisation_member_joins_that_organisation(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => $this->workspace->id]);
        $superAdmin->assignRole('super-admin');

        $this->create($superAdmin, 'analis@cyberdyne.test')->assertStatus(201);

        $this->assertSame($this->workspace->id, (int) User::where('email', 'analis@cyberdyne.test')->value('tenant_id'));
    }

    public function test_an_operator_creates_users_in_the_workspace_they_picked(): void
    {
        $operator = User::factory()->create(['tenant_id' => null]);
        $operator->assignRole('developer');

        $this->create($operator, 'tanpa.workspace@test.test')->assertStatus(422)->assertJsonValidationErrors('workspace');
        $this->assertNull(User::where('email', 'tanpa.workspace@test.test')->first());

        $this->actingAs($operator)->post(route('operator.workspace'), ['tenant' => $this->workspace->uuid])->assertSessionHasNoErrors();
        $this->create($operator, 'analis2@cyberdyne.test')->assertStatus(201);

        $this->assertSame($this->workspace->id, (int) User::where('email', 'analis2@cyberdyne.test')->value('tenant_id'));
    }
}
