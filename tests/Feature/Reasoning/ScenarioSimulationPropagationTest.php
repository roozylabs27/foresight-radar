<?php

declare(strict_types=1);

namespace Tests\Feature\Reasoning;

use App\Models\CrossImpact;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\ScenarioSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ScenarioSimulationPropagationTest extends TestCase
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
        $this->monitoringAction = StatusAction::create(['name' => 'Monitoring', 'symbol' => 'M', 'code' => 'monitoring', 'uuid' => Uuid::uuid1()]);
        $this->decidedPlanAction = StatusAction::create(['name' => 'Decided Plan', 'symbol' => 'DP', 'code' => 'decided_plan', 'uuid' => Uuid::uuid1()]);
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

    public function test_simulation_propagates_cross_impact_when_enabled(): void
    {
        $ratingA = $this->createApprovedRating('AI Automation', 8, 7, $this->monitoringAction);
        $ratingB = $this->createApprovedRating('Workforce Reskilling', 7, 8, $this->monitoringAction);

        $forceA = $ratingA->driving_force;
        $forceB = $ratingB->driving_force;

        // Cross-impact: AI Automation (A) strongly influences Workforce Reskilling (B) with score -3
        CrossImpact::create([
            'source_driving_force_id' => $forceA->id,
            'target_driving_force_id' => $forceB->id,
            'score' => -3,
        ]);

        $service = app(ScenarioSimulationService::class);

        // 1. Run direct simulation (propagate = false)
        $directResult = $service->simulate(null, null, [
            ['force' => $forceA->uuid, 'status_action' => 'decided_plan'],
        ], propagate: false);

        $this->assertFalse($directResult['propagation_enabled'] ?? false);
        $this->assertEmpty($directResult['propagations'] ?? []);

        // 2. Run propagated simulation (propagate = true)
        $propResult = $service->simulate(null, null, [
            ['force' => $forceA->uuid, 'status_action' => 'decided_plan'],
        ], propagate: true);

        $this->assertTrue($propResult['propagation_enabled'] ?? false);
        $this->assertNotEmpty($propResult['propagations'] ?? []);
        $this->assertSame($forceA->uuid, $propResult['propagations'][0]['source'] ?? null);
        $this->assertSame($forceB->uuid, $propResult['propagations'][0]['target'] ?? null);
        $this->assertSame(-3, $propResult['propagations'][0]['score'] ?? null);

        // 3. Invariant check: Assert database has NOT been mutated
        $this->assertDatabaseHas('driving_force_ratings', [
            'driving_force_id' => $forceA->id,
            'status_action_id' => $this->monitoringAction->id,
        ]);
        $this->assertDatabaseHas('driving_force_ratings', [
            'driving_force_id' => $forceB->id,
            'status_action_id' => $this->monitoringAction->id,
            'impact_analysis' => 7,
            'uncertainty_analysis' => 8,
        ]);
    }

    public function test_simulation_endpoint_handles_propagate_parameter(): void
    {
        $rating = $this->createApprovedRating('Inflation Hedge', 6, 5, $this->monitoringAction);
        $force = $rating->driving_force;

        $response = $this->actingAs($this->user)->postJson('/dashboard/simulate', [
            'overrides' => [
                ['force' => $force->uuid, 'status_action' => 'decided_plan'],
            ],
            'propagate' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('propagation_enabled', true);
        $this->assertIsArray($response->json('propagations'));
    }
}
