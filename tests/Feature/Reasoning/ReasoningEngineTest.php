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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReasoningEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Dimension $customerDim;
    private Dimension $techDim;
    private Dimension $ecoDim;
    private StatusAction $decidedPlanAction;
    private StatusAction $monitoringAction;
    private Priority $highPriority;
    private Priority $lowPriority;
    private TimeHorizon $shortTerm;

    protected function setUp(): void
    {
        parent::setUp();

        $viewDashboardPerm = Permission::create([
            'name' => 'view-dashboard',
            'uuid' => (string) Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => 'dashboard',
            'description' => 'View dashboard',
        ]);

        $adminRole = Role::create([
            'name' => 'admin',
            'uuid' => (string) Uuid::uuid1(),
            'guard_name' => 'web',
            'display_name' => 'Administrator',
        ]);
        $adminRole->givePermissionTo($viewDashboardPerm);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $macroEnv = Environment::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Macro']);
        $mezzoEnv = Environment::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Mezzo']);

        $this->customerDim = Dimension::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Customer', 'environment_id' => $mezzoEnv->id]);
        $this->techDim = Dimension::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Technology', 'environment_id' => $macroEnv->id]);
        $this->ecoDim = Dimension::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Ecology', 'environment_id' => $macroEnv->id]);

        $this->decidedPlanAction = StatusAction::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Decided Plan', 'symbol' => 'circle', 'code' => 'O']);
        $this->monitoringAction = StatusAction::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Monitoring', 'symbol' => 'triangle', 'code' => '∆']);

        $this->highPriority = Priority::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'High', 'color' => 'red']);
        $this->lowPriority = Priority::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Low', 'color' => 'green']);

        $this->shortTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Short Term', 'code' => 'ST']);
    }

    private function createForceWithRating(
        string $keyword,
        int $dimensionId,
        int $impact,
        int $uncertainty,
        int $statusActionId,
        int $priorityId
    ): DrivingForce {
        $df = DrivingForce::create([
            'uuid' => (string) Uuid::uuid1(),
            'keyword' => $keyword,
            'description' => 'Detailed description of the strategic driving force under investigation.',
            'status' => 'APPROVED',
            'dimension_id' => $dimensionId,
            'pic' => $this->admin->id,
            'created_by' => $this->admin->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DrivingForceRating::create([
            'uuid' => (string) Uuid::uuid1(),
            'driving_force_id' => $df->id,
            'impact_analysis' => $impact,
            'uncertainty_analysis' => $uncertainty,
            'status_action_id' => $statusActionId,
            'priority_id' => $priorityId,
            'time_horizon_id' => $this->shortTerm->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $df;
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->getJson('/dashboard/reasoning-data');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_receives_403(): void
    {
        $unauthorizedUser = User::factory()->create();
        $response = $this->actingAs($unauthorizedUser)->getJson('/dashboard/reasoning-data');
        $response->assertStatus(403);
    }

    public function test_empty_dataset_returns_neutral_baseline_scores(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/dashboard/reasoning-data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'growth_index',
            'resilience_index',
            'readiness_status',
            'readiness_badge',
            'top_critical_forces',
            'eda_frequency',
            'eda_trend',
        ]);

        $this->assertEquals(50.0, $response->json('growth_index'));
        $this->assertEquals(50.0, $response->json('resilience_index'));
        $this->assertEmpty($response->json('top_critical_forces'));
    }

    public function test_reasoning_engine_calculates_growth_and_resilience_accurately(): void
    {
        // 1. Force with Decided Plan on opportunity dimension (Customer) -> strengthens both Growth and Resilience
        $this->createForceWithRating(
            'AI Automated Customer Support',
            $this->customerDim->id,
            8,
            3,
            $this->decidedPlanAction->id,
            $this->highPriority->id
        );

        // 2. High Impact force still under Monitoring -> triggers risk exposure penalty on Resilience
        $df2 = $this->createForceWithRating(
            'Critical Tech Infrastructure Outage',
            $this->techDim->id,
            9,
            8,
            $this->monitoringAction->id,
            $this->highPriority->id
        );

        $response = $this->actingAs($this->admin)->getJson('/dashboard/reasoning-data');

        $response->assertStatus(200);

        $growth = $response->json('growth_index');
        $resilience = $response->json('resilience_index');

        $this->assertGreaterThan(50.0, $growth);
        $this->assertLessThan(60.0, $resilience);

        // Top critical forces should prioritize high-impact force under monitoring (df2)
        $topForces = $response->json('top_critical_forces');
        $this->assertNotEmpty($topForces);
        $this->assertEquals($df2->keyword, $topForces[0]['name']);
        $this->assertEquals('Monitoring', $topForces[0]['status_action']);
    }

    public function test_reasoning_data_respects_dimension_filter(): void
    {
        $this->createForceWithRating(
            'Customer Force Only',
            $this->customerDim->id,
            9,
            2,
            $this->decidedPlanAction->id,
            $this->highPriority->id
        );

        $this->createForceWithRating(
            'Carbon Tax Regulatory Shift',
            $this->ecoDim->id,
            8,
            6,
            $this->decidedPlanAction->id,
            $this->highPriority->id
        );

        // Filter by Ecology dimension
        $response = $this->actingAs($this->admin)->getJson("/dashboard/reasoning-data?dimension={$this->ecoDim->id}");

        $response->assertStatus(200);
        $topForces = $response->json('top_critical_forces');
        $this->assertCount(1, $topForces);
        $this->assertEquals('Carbon Tax Regulatory Shift', $topForces[0]['name']);
    }
}
