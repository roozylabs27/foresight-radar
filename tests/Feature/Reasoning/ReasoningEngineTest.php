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

/**
 * Expected values follow docs/product/REASONING_ENGINE.md, with
 * I = impact / 10 and U = (uncertainty − 1) / 9.
 */
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
    private TimeHorizon $midTerm;
    private TimeHorizon $longTerm;

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
        $this->midTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Mid Term', 'code' => 'MT']);
        $this->longTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid1(), 'name' => 'Long Term', 'code' => 'LT']);
    }

    private function createForceWithRating(
        string $keyword,
        int $dimensionId,
        int $impact,
        int $uncertainty,
        int $statusActionId,
        int $priorityId,
        ?int $timeHorizonId = null,
        ?Carbon $createdAt = null
    ): DrivingForce {
        $df = DrivingForce::create([
            'uuid' => (string) Uuid::uuid1(),
            'keyword' => $keyword,
            'description' => 'Detailed description of the strategic driving force under investigation.',
            'status' => 'APPROVED',
            'dimension_id' => $dimensionId,
            'pic' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $rating = DrivingForceRating::create([
            'uuid' => (string) Uuid::uuid1(),
            'driving_force_id' => $df->id,
            'impact_analysis' => $impact,
            'uncertainty_analysis' => $uncertainty,
            'status_action_id' => $statusActionId,
            'priority_id' => $priorityId,
            'time_horizon_id' => $timeHorizonId ?? $this->shortTerm->id,
        ]);

        if ($createdAt) {
            $rating->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $df;
    }

    private function reasoning(string $query = '')
    {
        $response = $this->actingAs($this->admin)->getJson('/dashboard/reasoning-data' . $query);
        $response->assertStatus(200);

        return $response;
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
        $response = $this->reasoning();

        $response->assertJsonStructure([
            'growth_index',
            'resilience_index',
            'readiness_status',
            'readiness_badge',
            'top_critical_forces',
            'explanation' => ['baseline', 'thresholds', 'posture_rule', 'growth', 'resilience'],
            'eda_frequency',
            'eda_distribution',
            'eda_relationship',
            'eda_comparison',
            'eda_trend',
        ]);

        $this->assertEquals(50.0, $response->json('growth_index'));
        $this->assertEquals(50.0, $response->json('resilience_index'));
        $this->assertSame('Vulnerable (Needs Attention)', $response->json('readiness_status'));
        $this->assertEmpty($response->json('top_critical_forces'));
        $this->assertEmpty($response->json('explanation.growth.contributions'));
    }

    public function test_posture_agile_and_resilient_when_both_indices_reach_threshold(): void
    {
        // R: +50·0.8·(1−(2/9)/2) = +35.56 and +50·0.9·(1−(1/9)/2) = +42.5 → 50 + 39.03
        // G: +50·0.8·(1−2/9) = +31.11 and +50·0.9·(1−1/9) = +40.0 → 50 + 35.56
        $this->createForceWithRating('Customer Self Service', $this->customerDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Cloud Platform Rollout', $this->techDim->id, 9, 2, $this->decidedPlanAction->id, $this->highPriority->id);

        $response = $this->reasoning();

        $this->assertEquals(89.0, $response->json('resilience_index'));
        $this->assertEquals(85.6, $response->json('growth_index'));
        $this->assertSame('Agile & Resilient', $response->json('readiness_status'));
        $this->assertSame('Resilience 89,0 ≥ 60 dan Growth 85,6 ≥ 60 → Agile & Resilient', $response->json('explanation.posture_rule'));
    }

    public function test_posture_defensive_prepared_when_only_resilience_reaches_threshold(): void
    {
        // R: Ecology Decided Plan +35.56 (w 1) and Customer Monitoring impact 6 < 7 → 0 (w 0.75) → 50 + 35.56/1.75
        // G: Customer Monitoring −25·0.6·(4/9) = −6.67 → 43.3; Ecology is not an opportunity dimension
        $this->createForceWithRating('Carbon Capture Program', $this->ecoDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Customer Loyalty Shift', $this->customerDim->id, 6, 5, $this->monitoringAction->id, $this->lowPriority->id, $this->midTerm->id);

        $response = $this->reasoning();

        $this->assertEquals(70.3, $response->json('resilience_index'));
        $this->assertEquals(43.3, $response->json('growth_index'));
        $this->assertSame('Defensive Prepared', $response->json('readiness_status'));
    }

    public function test_posture_growth_aggressive_when_only_growth_reaches_threshold(): void
    {
        // R: Customer Decided Plan +50 and Ecology Monitoring −50·0.9·(7/9) = −35 → 50 + 7.5
        // G: Customer Decided Plan +50 → 100
        $this->createForceWithRating('Direct To Consumer', $this->customerDim->id, 10, 1, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Water Scarcity Risk', $this->ecoDim->id, 9, 8, $this->monitoringAction->id, $this->highPriority->id);

        $response = $this->reasoning();

        $this->assertEquals(57.5, $response->json('resilience_index'));
        $this->assertEquals(100.0, $response->json('growth_index'));
        $this->assertSame('Growth-Aggressive (Exposed)', $response->json('readiness_status'));
    }

    public function test_posture_vulnerable_and_critical_forces_rank_exposed_monitoring_first(): void
    {
        // R: +35.56 and −50·0.9·(7/9) = −35 → 50 + 0.28
        // G: +31.11 and −25·0.9·(7/9) = −17.5 → 50 + 6.81
        $this->createForceWithRating('AI Automated Customer Support', $this->customerDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $df2 = $this->createForceWithRating('Critical Tech Infrastructure Outage', $this->techDim->id, 9, 8, $this->monitoringAction->id, $this->highPriority->id);

        $response = $this->reasoning();

        $this->assertEquals(50.3, $response->json('resilience_index'));
        $this->assertEquals(56.8, $response->json('growth_index'));
        $this->assertSame('Vulnerable (Needs Attention)', $response->json('readiness_status'));

        $topForces = $response->json('top_critical_forces');
        $this->assertEquals($df2->keyword, $topForces[0]['name']);
        $this->assertEquals('Monitoring', $topForces[0]['status_action']);
    }

    public function test_contributions_explain_the_distance_from_baseline(): void
    {
        $this->createForceWithRating('AI Automated Customer Support', $this->customerDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Critical Tech Infrastructure Outage', $this->techDim->id, 9, 8, $this->monitoringAction->id, $this->highPriority->id);
        $this->createForceWithRating('Carbon Capture Program', $this->ecoDim->id, 5, 5, $this->monitoringAction->id, $this->lowPriority->id, $this->longTerm->id);

        $response = $this->reasoning();

        foreach (['resilience', 'growth'] as $index) {
            $explanation = $response->json("explanation.{$index}");
            $sum = array_sum(array_column($explanation['contributions'], 'contribution'));
            $this->assertEqualsWithDelta($response->json("{$index}_index") - 50.0, $sum, 0.1, "{$index} contributions");
        }

        // Resilience weights: ST 1 + ST 1 + LT 0.5 = 2.5 → +35.56/2.5, −35/2.5, 0
        $resilience = $response->json('explanation.resilience');
        $this->assertEquals(2.5, $resilience['total_weight']);
        $this->assertCount(3, $resilience['contributions']);
        $this->assertSame('AI Automated Customer Support', $resilience['contributions'][0]['keyword']);
        $this->assertEquals(14.22, $resilience['contributions'][0]['contribution']);
        $this->assertSame('positive', $resilience['contributions'][0]['effect']);
        $this->assertSame('Decided Plan, impact 8, uncertainty 3 → mitigasi terencana', $resilience['contributions'][0]['rule']);
        $this->assertEquals(-14.0, $resilience['contributions'][1]['contribution']);
        $this->assertSame('negative', $resilience['contributions'][1]['effect']);
        $this->assertSame('Monitoring, impact 9 ≥ 7, uncertainty 8 → eksposur risiko', $resilience['contributions'][1]['rule']);
        $this->assertSame('Monitoring, impact 5 < 7 → netral', $resilience['contributions'][2]['rule']);
        $this->assertSame('neutral', $resilience['contributions'][2]['effect']);
        $this->assertEquals(50.2, $response->json('resilience_index'));

        // Growth only counts the Customer and Technology forces
        $growth = $response->json('explanation.growth');
        $this->assertCount(2, $growth['contributions']);
        $this->assertSame('Decided Plan di Customer, impact 8, uncertainty 3 → peluang ditangkap', $growth['contributions'][0]['rule']);
        $this->assertEquals(15.56, $growth['contributions'][0]['contribution']);
        $this->assertEquals(-8.75, $growth['contributions'][1]['contribution']);
    }

    public function test_short_term_forces_weigh_more_on_resilience(): void
    {
        // Decided Plan +35.56 at Long Term (w 0.5), Monitoring −35 at Short Term (w 1)
        // → 50 + (0.5·35.56 − 35) / 1.5 = 38.5
        $this->createForceWithRating('Carbon Capture Program', $this->ecoDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id, $this->longTerm->id);
        $this->createForceWithRating('Water Scarcity Risk', $this->ecoDim->id, 9, 8, $this->monitoringAction->id, $this->highPriority->id);

        $this->assertEquals(38.5, $this->reasoning()->json('resilience_index'));
    }

    public function test_reasoning_data_respects_dimension_filter(): void
    {
        $this->createForceWithRating('Customer Force Only', $this->customerDim->id, 9, 2, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Carbon Tax Regulatory Shift', $this->ecoDim->id, 8, 6, $this->decidedPlanAction->id, $this->highPriority->id);

        $response = $this->reasoning("?dimension={$this->ecoDim->id}");

        $topForces = $response->json('top_critical_forces');
        $this->assertCount(1, $topForces);
        $this->assertEquals('Carbon Tax Regulatory Shift', $topForces[0]['name']);
        $this->assertEquals(50.0, $response->json('growth_index'));
    }

    public function test_eda_datasets_describe_distribution_relationship_and_comparison(): void
    {
        $this->createForceWithRating('Customer Self Service', $this->customerDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Infrastructure Outage', $this->techDim->id, 9, 8, $this->monitoringAction->id, $this->highPriority->id);
        $this->createForceWithRating('Carbon Capture Program', $this->ecoDim->id, 4, 6, $this->decidedPlanAction->id, $this->lowPriority->id, $this->midTerm->id);
        $this->createForceWithRating('Customer Loyalty Shift', $this->customerDim->id, 6, 5, $this->monitoringAction->id, $this->lowPriority->id, $this->longTerm->id);

        $response = $this->reasoning();

        // Frequency
        $frequency = collect($response->json('eda_frequency'))->keyBy('name');
        $this->assertSame(['count' => 2, 'decided_count' => 1, 'monitoring_count' => 1], collect($frequency['Customer'])->only(['count', 'decided_count', 'monitoring_count'])->all());
        $this->assertSame(1, $frequency['Technology']['monitoring_count']);

        // Distribution
        $bins = collect($response->json('eda_distribution.bins'))->keyBy('score');
        $this->assertCount(10, $bins);
        $this->assertSame(1, $bins[4]['impact']);
        $this->assertSame(0, $bins[5]['impact']);
        $this->assertSame(1, $bins[5]['uncertainty']);
        $this->assertEquals(
            ['min' => 4, 'q1' => 5.5, 'median' => 7, 'q3' => 8.25, 'max' => 9, 'mean' => 6.75],
            $response->json('eda_distribution.impact')
        );

        // Relationship: x = [8, 9, 4, 6], y = [3, 8, 6, 5] → r = 1.5 / √(14.75 · 13)
        $this->assertSame(4, $response->json('eda_relationship.n'));
        $this->assertEquals(0.108, $response->json('eda_relationship.pearson_r'));
        $this->assertSame('lemah positif', $response->json('eda_relationship.strength'));

        // Comparison
        $byDimension = $response->json('eda_comparison.by_dimension');
        $this->assertSame(['Customer', 'Technology', 'Ecology'], array_column($byDimension, 'name'));
        $this->assertEquals(['name' => 'Customer', 'count' => 2, 'min' => 6, 'q1' => 6.5, 'median' => 7, 'q3' => 7.5, 'max' => 8, 'mean' => 7], $byDimension[0]);
        $byHorizon = $response->json('eda_comparison.by_horizon');
        $this->assertSame(['Short Term', 'Mid Term', 'Long Term'], array_column($byHorizon, 'name'));
        $this->assertEquals(8.5, $byHorizon[0]['median']);
        $this->assertSame(2, $byHorizon[0]['count']);
    }

    public function test_pearson_correlation_is_null_without_variance(): void
    {
        $this->createForceWithRating('Customer Self Service', $this->customerDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Infrastructure Outage', $this->techDim->id, 8, 8, $this->monitoringAction->id, $this->highPriority->id);

        $response = $this->reasoning();

        $this->assertSame(2, $response->json('eda_relationship.n'));
        $this->assertNull($response->json('eda_relationship.pearson_r'));
    }

    public function test_trend_covers_the_last_twelve_months_by_default(): void
    {
        $this->createForceWithRating('Customer Self Service', $this->customerDim->id, 8, 3, $this->decidedPlanAction->id, $this->highPriority->id);
        $this->createForceWithRating('Infrastructure Outage', $this->techDim->id, 9, 8, $this->monitoringAction->id, $this->highPriority->id);
        $this->createForceWithRating('Earlier Ecology Force', $this->ecoDim->id, 4, 6, $this->monitoringAction->id, $this->lowPriority->id, null, Carbon::now()->startOfMonth()->subMonths(3)->addDays(4));

        $response = $this->reasoning();

        // The selected period (this month) holds two forces; the trend reaches back further.
        $this->assertSame(2, $response->json('total_active_forces'));

        $trend = collect($response->json('eda_trend'));
        $this->assertCount(12, $trend);
        $this->assertSame(Carbon::now()->startOfMonth()->subMonths(11)->format('Y-m'), $trend->first()['period']);

        $current = $trend->last();
        $this->assertSame(Carbon::now()->format('Y-m'), $current['period']);
        $this->assertSame(['total' => 2, 'decided_plan' => 1, 'monitoring' => 1], collect($current)->only(['total', 'decided_plan', 'monitoring'])->all());
        $this->assertEquals(8.5, $current['avg_impact']);
        $this->assertEquals(5.5, $current['avg_uncertainty']);

        $earlier = $trend->firstWhere('period', Carbon::now()->startOfMonth()->subMonths(3)->format('Y-m'));
        $this->assertSame(1, $earlier['total']);
        $this->assertSame(1, $earlier['monitoring']);

        $empty = $trend->firstWhere('period', Carbon::now()->startOfMonth()->subMonths(1)->format('Y-m'));
        $this->assertSame(0, $empty['total']);
        $this->assertNull($empty['avg_impact']);
    }

    public function test_trend_keeps_a_selected_period_longer_than_twelve_months(): void
    {
        $start = Carbon::now()->startOfMonth()->subMonths(17)->format('Y-m-d');
        $end = Carbon::now()->endOfMonth()->format('Y-m-d');

        $response = $this->reasoning("?date[]={$start}&date[]={$end}");

        $this->assertCount(18, $response->json('eda_trend'));
        $this->assertSame([$start, $end], $response->json('eda_trend_range'));
    }
}
