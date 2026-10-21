<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementFilterTest extends TestCase
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

    public function test_user_fetch_data_succeeds_without_date_parameter(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $adminRole = $this->createRoleWithDefaults('super-admin');
        $adminRole->givePermissionTo([$viewPerm]);
        $userRole = $this->createRoleWithDefaults('user');

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        User::factory()->count(3)->create()->each(function ($user) use ($userRole) {
            $user->assignRole($userRole);
        });

        // Call fetch_data without date parameters
        $response = $this->actingAs($admin)->getJson('/user-management/user/fetch-data?pagination[pageSize]=10&pagination[current]=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'total',
            ],
        ]);
        $total = $response->json('meta.total') ?? $response->json('total');
        $this->assertGreaterThanOrEqual(4, $total);
    }

    public function test_user_fetch_data_searches_by_name_without_date_parameter(): void
    {
        $viewPerm = $this->createPermissionWithDefaults('view-user');
        $adminRole = $this->createRoleWithDefaults('super-admin');
        $adminRole->givePermissionTo([$viewPerm]);
        $userRole = $this->createRoleWithDefaults('user');

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $target = User::factory()->create(['name' => 'Alexander Supertramp']);
        $target->assignRole($userRole);

        $response = $this->actingAs($admin)->getJson('/user-management/user/fetch-data?search=Alexander&pagination[pageSize]=10&pagination[current]=1');

        $response->assertStatus(200);
        $total = $response->json('meta.total') ?? $response->json('total');
        $this->assertEquals(1, $total);
        $this->assertEquals('Alexander Supertramp', $response->json('data.0.name'));
    }
}
