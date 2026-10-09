<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleEscalationTest extends TestCase
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

    public function test_regular_admin_cannot_assign_super_admin_role_on_create(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $createPerm = $this->createPermissionWithDefaults('create-user');

        $superAdminRole = $this->createRoleWithDefaults('super-admin');
        $adminRole = $this->createRoleWithDefaults('admin');
        $adminRole->givePermissionTo([$viewPerm, $createPerm]);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->postJson('/user-management/user', [
            'type' => 'create',
            'name' => 'New Privileged User',
            'email' => 'privileged@example.com',
            'password' => 'Password123!',
            'role_id' => $superAdminRole->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'privileged@example.com']);
    }

    public function test_regular_admin_cannot_assign_super_admin_role_on_update(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $updatePerm = $this->createPermissionWithDefaults('update-user');

        $superAdminRole = $this->createRoleWithDefaults('super-admin');
        $adminRole = $this->createRoleWithDefaults('admin');
        $staffRole = $this->createRoleWithDefaults('staff');
        $adminRole->givePermissionTo([$viewPerm, $updatePerm]);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $targetUser = User::factory()->create();
        $targetUser->assignRole($staffRole);

        $response = $this->actingAs($admin)->putJson("/user-management/user/{$targetUser->uuid}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role_id' => $superAdminRole->id,
        ]);

        $response->assertStatus(422);
        $targetUser->refresh();
        $this->assertFalse($targetUser->hasRole('super-admin'));
    }

    public function test_super_admin_can_assign_super_admin_role(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $createPerm = $this->createPermissionWithDefaults('create-user');

        $superAdminRole = $this->createRoleWithDefaults('super-admin');
        $superAdminRole->givePermissionTo([$viewPerm, $createPerm]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $response = $this->actingAs($superAdmin)->postJson('/user-management/user', [
            'type' => 'create',
            'name' => 'Another Super Admin',
            'email' => 'another.super@example.com',
            'password' => 'Password123!',
            'role_id' => $superAdminRole->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'another.super@example.com']);
    }
}
