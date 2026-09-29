<?php

namespace Tests\Feature;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RatingUrgencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private DrivingForceRating $rating;

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

        $this->makePermission('view-rating-urgency');
        $this->makePermission('create-rating-urgency');

        $role = Role::create([
            'uuid' => fake()->uuid(),
            'name' => 'admin',
            'display_name' => 'Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo(['view-rating-urgency', 'create-rating-urgency']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->syncPermissions($role->permissions);

        // Priorities
        Priority::create(['id' => 1, 'uuid' => fake()->uuid(), 'name' => 'High', 'color' => '#ff4d4f']);
        Priority::create(['id' => 2, 'uuid' => fake()->uuid(), 'name' => 'Medium', 'color' => '#faad14']);
        Priority::create(['id' => 3, 'uuid' => fake()->uuid(), 'name' => 'Low', 'color' => '#52c41a']);

        $timeHorizon = TimeHorizon::create(['id' => 1, 'uuid' => fake()->uuid(), 'name' => 'Short Term', 'code' => 'ST']);

        $env = Environment::create(['uuid' => fake()->uuid(), 'name' => 'External']);
        $dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Economy',
            'environment_id' => $env->id,
        ]);

        $drivingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Test',
            'description' => 'Test description',
            'dimension_id' => $dimension->id,
            'created_by' => $this->admin->id,
            'pic' => $this->admin->id,
            'status' => 'PENDING',
        ]);

        $this->rating = DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $drivingForce->id,
            'time_horizon_id' => $timeHorizon->id,
        ]);
    }

    public function test_can_set_impact_analysis(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            "/rating-urgency/{$this->rating->uuid}",
            ['type' => 'impact', 'value' => 7]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('driving_force_ratings', [
            'id' => $this->rating->id,
            'impact_analysis' => 7,
        ]);
    }

    public function test_priority_is_calculated_when_both_values_set(): void
    {
        $this->rating->update(['impact_analysis' => 7]);

        $response = $this->actingAs($this->admin)->postJson(
            "/rating-urgency/{$this->rating->uuid}",
            ['type' => 'uncertainty', 'value' => 8]
        );

        $response->assertStatus(200);
        $this->rating->refresh();
        // Both >= 6, should be priority 1 (High)
        $this->assertEquals(1, $this->rating->priority_id);
    }

    public function test_priority_low_when_both_values_below_threshold(): void
    {
        $this->rating->update(['impact_analysis' => 3]);

        $response = $this->actingAs($this->admin)->postJson(
            "/rating-urgency/{$this->rating->uuid}",
            ['type' => 'uncertainty', 'value' => 4]
        );

        $response->assertStatus(200);
        $this->rating->refresh();
        // Both < 6, should be priority 3 (Low)
        $this->assertEquals(3, $this->rating->priority_id);
    }
}
