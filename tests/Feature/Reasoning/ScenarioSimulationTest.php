<?php

namespace Tests\Feature\Reasoning;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ScenarioSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Dimension $techDimension;
    private Priority $highPriority;
    private StatusAction $monitoringAction;
    private StatusAction $decidedPlanAction;
    private TimeHorizon $shortTermHorizon;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'view-dashboard', 'guard_name' => 'web'], [
            'uuid' => Uuid::uuid1(),
            'category' => 'general',
            'description' => 'Permission to view dashboard',
        ]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('view-dashboard');

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $this->techDimension = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);
        $this->highPriority = Priority::create(['uuid' => Uuid::uuid1(), 'name' => 'High', 'color' => '#ff4d4f']);
        $this->monitoringAction = StatusAction::create(['name' => 'Monitoring', 'symbol' => 'M', 'code' => '∆', 'uuid' => Uuid::uuid1()]);
        $this->decidedPlanAction = StatusAction::create(['name' => 'Decided Plan', 'symbol' => 'DP', 'code' => 'O', 'uuid' => Uuid::uuid1()]);
        $this->shortTermHorizon = TimeHorizon::create(['name' => 'Short Term', 'code' => 'ST', 'uuid' => Uuid::uuid1()]);
    }

    private function createApprovedRating(string $keyword, int $impact, int $uncertainty, StatusAction $action): DrivingForceRating
    {
        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'keyword' => $keyword,
            'description' => 'Test force description',
            'dimension_id' => $this->techDimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'APPROVED',
        ]);

        return DrivingForceRating::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_id' => $df->id,
            'impact_analysis' => $impact,
            'uncertainty_analysis' => $uncertainty,
            'priority_id' => $this->highPriority->id,
            'status_action_id' => $action->id,
            'time_horizon_id' => $this->shortTermHorizon->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->postJson('/dashboard/simulate', [
            'overrides' => [
                ['force' => Uuid::uuid1()->toString(), 'impact' => 5],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_simulates_impact_and_status_action_changes_without_persisting(): void
    {
        $rating = $this->createApprovedRating('Quantum AI', 8, 8, $this->monitoringAction);

        $response = $this->actingAs($this->user)->postJson('/dashboard/simulate', [
            'overrides' => [
                [
                    'force' => $rating->driving_force->uuid,
                    'status_action' => 'decided_plan',
                    'impact' => 4,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('baseline', $data);
        $this->assertArrayHasKey('simulation', $data);
        $this->assertArrayHasKey('deltas', $data);

        // Verify database did not change
        $rating->refresh();
        $this->assertEquals(8, $rating->impact_analysis);
        $this->assertEquals($this->monitoringAction->id, $rating->status_action_id);
    }

    public function test_rejects_force_outside_scope_with_422(): void
    {
        $foreignUuid = Uuid::uuid1()->toString();
        // Create force but with status DRAFT so it won't be approved in scope
        DrivingForce::create([
            'uuid' => $foreignUuid,
            'keyword' => 'Draft Force',
            'description' => 'Draft',
            'dimension_id' => $this->techDimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($this->user)->postJson('/dashboard/simulate', [
            'overrides' => [
                [
                    'force' => $foreignUuid,
                    'impact' => 5,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
