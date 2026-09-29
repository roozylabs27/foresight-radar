<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePermission(string $name): Permission
    {
        return Permission::create([
            'uuid' => fake()->uuid(),
            'name' => $name,
            'description' => $name,
            'category' => 'general',
            'guard_name' => 'web',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makePermission('view-driving-force');
        $this->makePermission('view-dashboard');

        Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'admin',
            'display_name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $bodRole = Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'bod',
            'display_name' => 'Board of Directors',
            'guard_name' => 'web',
        ]);
        $bodRole->givePermissionTo(['view-driving-force', 'view-dashboard']);
    }

    public function test_user_without_create_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('bod');

        $this->makePermission('create-driving-force');

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $response = $this->actingAs($user)->postJson('/driving-force', [
            'keyword' => 'Unauthorized Signal',
            'description' => 'Should fail due to missing permission',
            'dimension_id' => $dimension->id,
            'pic_id' => $user->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_non_owner_without_admin_role_cannot_delete_driving_force(): void
    {
        $this->makePermission('delete-driving-force');

        $owner = User::factory()->create();
        $owner->assignRole('bod');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('bod');
        $otherUser->givePermissionTo('delete-driving-force');

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Protected',
            'description' => 'Cannot delete by non-owner non-admin',
            'dimension_id' => $dimension->id,
            'created_by' => $owner->id,
            'pic' => $owner->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($otherUser)
            ->deleteJson("/driving-force/{$drivingForce->uuid}");

        $response->assertStatus(403);
    }
}
