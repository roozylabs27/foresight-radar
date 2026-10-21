<?php

namespace Tests\Feature\Reliability;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createRoleWithDefaults(string $name): Role
    {
        return Role::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'display_name' => ucfirst($name),
        ]);
    }

    private function createPermissionWithDefaults(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => 'user',
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_creating_user_with_duplicate_name_does_not_overwrite_existing_user_roles(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $createPerm = $this->createPermissionWithDefaults('create-user');

        $adminRole = $this->createRoleWithDefaults('admin');
        $adminRole->givePermissionTo([$viewPerm, $createPerm]);

        $staffRole = $this->createRoleWithDefaults('staff');
        $managerRole = $this->createRoleWithDefaults('manager');

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        // Pre-existing user named "John Doe" with staff role
        $existing = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.original@example.com',
        ]);
        $existing->assignRole($staffRole);

        // Admin creates a second user also named "John Doe" but with different email and manager role
        $response = $this->actingAs($admin)->postJson('/user-management/user', [
            'type' => 'create',
            'name' => 'John Doe',
            'email' => 'john.second@example.com',
            'password' => 'Password123!',
            'role_id' => $managerRole->id,
        ]);

        $response->assertStatus(201);

        // Existing user's role must NOT be overwritten
        $existing->refresh();
        $this->assertTrue($existing->hasRole('staff'), 'Original user role was overwritten!');
        $this->assertFalse($existing->hasRole('manager'), 'Original user erroneously gained manager role!');

        // Second user must be created independently
        $this->assertDatabaseHas('users', ['email' => 'john.second@example.com']);
        $secondUser = User::where('email', 'john.second@example.com')->first();
        $this->assertTrue($secondUser->hasRole('manager'));
    }
}
