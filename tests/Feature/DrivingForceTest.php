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

class DrivingForceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Dimension $dimension;

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
        $this->makePermission('create-driving-force');
        $this->makePermission('update-driving-force');
        $this->makePermission('delete-driving-force');
        $this->makePermission('view-dashboard');

        $role = Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'admin',
            'display_name' => 'Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'view-driving-force',
            'create-driving-force',
            'update-driving-force',
            'delete-driving-force',
            'view-dashboard',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->syncPermissions($role->permissions);

        $env = Environment::create([
            'uuid' => fake()->uuid(),
            'name' => 'External',
        ]);

        $this->dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);
    }

    public function test_authenticated_user_can_view_driving_force_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/driving-force');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_driving_force(): void
    {
        $response = $this->get('/driving-force');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_driving_force(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/driving-force', [
            'keyword' => 'Test Signal',
            'description' => 'Test description for signal',
            'dimension_id' => $this->dimension->id,
            'pic_id' => $this->admin->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('driving_forces', ['keyword' => 'Test Signal']);
    }

    public function test_admin_can_delete_own_driving_force(): void
    {
        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'To Delete',
            'description' => 'Will be deleted',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/driving-force/{$drivingForce->uuid}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('driving_forces', ['id' => $drivingForce->id]);
    }
}
