<?php

namespace Tests\Feature\Reasoning;

use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\RatingHistoryAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RatingHistoryAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const RANGE = ['2025-11-01', '2026-10-31'];

    private User $analyst;
    private Dimension $economy;
    private Dimension $ecology;
    private StatusAction $monitoring;
    private StatusAction $decided;
    private TimeHorizon $shortTerm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyst = User::factory()->create();
        $environment = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $this->economy = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Economy', 'environment_id' => $environment->id]);
        $this->ecology = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Ecology', 'environment_id' => $environment->id]);
        $this->monitoring = StatusAction::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Monitoring', 'symbol' => 'triangle', 'code' => '∆']);
        $this->decided = StatusAction::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Decided Plan', 'symbol' => 'circle', 'code' => 'O']);
        $this->shortTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Short Term', 'code' => 'ST']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rate(string $keyword, Dimension $dimension, int $impact, int $uncertainty): DrivingForceRating
    {
        $df = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => $keyword,
            'description' => 'Driving force with a rating history.',
            'status' => 'APPROVED',
            'dimension_id' => $dimension->id,
            'pic' => $this->analyst->id,
            'created_by' => $this->analyst->id,
        ]);
        $rating = DrivingForceRating::create([
            'driving_force_id' => $df->id,
            'time_horizon_id' => $this->shortTerm->id,
            'status_action_id' => $this->monitoring->id,
            'impact_analysis' => $impact,
            'uncertainty_analysis' => $uncertainty,
        ]);
        $this->decide($rating, $this->monitoring);

        return $rating;
    }

    private function decide(DrivingForceRating $rating, StatusAction $action): void
    {
        $rating->update(['status_action_id' => $action->id]);
        ActionReason::create([
            'uuid' => (string) Uuid::uuid4(),
            'driving_force_rating_id' => $rating->id,
            'status_action_id' => $action->id,
            'date' => Carbon::now(),
            'reason' => "Set to {$action->name}",
        ]);
    }

    /**
     * Interest Rate (Economy): rated 4/6 in August, impact raised to 6 in
     * September, Decided Plan on 5 October. Water Stress (Ecology): rated 8/2
     * on 1 October and still Monitoring on 16 October.
     */
    private function buildHistory(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $interest = $this->rate('Interest Rate', $this->economy, 4, 6);

        Carbon::setTestNow('2026-09-15 09:00:00');
        $interest->update(['impact_analysis' => 6]);

        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->rate('Water Stress', $this->ecology, 8, 2);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->decide($interest, $this->decided);

        Carbon::setTestNow('2026-10-16 09:00:00');
    }

    public function test_score_trend_uses_each_ratings_latest_state_per_month(): void
    {
        $this->buildHistory();

        $trend = app(RatingHistoryAnalyticsService::class)->scoreTrend(self::RANGE);

        $this->assertSame('2026-08-10', $trend['since']);
        $this->assertSame(2, $trend['updates']);
        $this->assertSame(3, $trend['months_with_data']);
        $this->assertTrue($trend['enough']);

        $overall = collect($trend['overall']['points'])->keyBy('period');
        $this->assertCount(12, $overall);
        $this->assertSame(0, $overall['2026-07']['n']);
        $this->assertNull($overall['2026-07']['avg_impact']);
        $this->assertEquals(['period' => '2026-08', 'n' => 1, 'avg_impact' => 4, 'avg_uncertainty' => 6], $overall['2026-08']);
        $this->assertEquals(['period' => '2026-09', 'n' => 1, 'avg_impact' => 6, 'avg_uncertainty' => 6], $overall['2026-09']);
        $this->assertEquals(['period' => '2026-10', 'n' => 2, 'avg_impact' => 7, 'avg_uncertainty' => 4], $overall['2026-10']);
        $this->assertEquals(3, $trend['overall']['impact_change']);
        $this->assertEquals(-2, $trend['overall']['uncertainty_change']);

        $dimensions = collect($trend['dimensions'])->keyBy('name');
        $this->assertSame(['Economy', 'Ecology'], $dimensions->keys()->all());
        $this->assertEquals(2, $dimensions['Economy']['impact_change']);
        $this->assertNull($dimensions['Ecology']['impact_change']);
    }

    public function test_score_trend_is_not_enough_with_a_single_month_and_no_changes(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->rate('Water Stress', $this->ecology, 8, 2);

        $trend = app(RatingHistoryAnalyticsService::class)->scoreTrend(self::RANGE);

        $this->assertSame(1, $trend['months_with_data']);
        $this->assertFalse($trend['enough']);
    }

    public function test_transition_velocity_measures_days_from_monitoring_to_decided_plan(): void
    {
        $this->buildHistory();

        $velocity = app(RatingHistoryAnalyticsService::class)->transitionVelocity(self::RANGE);

        $this->assertSame(1, $velocity['count']);
        $this->assertSame('Interest Rate', $velocity['transitions'][0]['keyword']);
        $this->assertSame('2026-08-10', $velocity['transitions'][0]['monitoring_since']);
        $this->assertSame('2026-10-05', $velocity['transitions'][0]['decided_at']);
        $this->assertEquals(56.0, $velocity['transitions'][0]['days']);
        $this->assertEquals(56.0, $velocity['median_days']);
        $this->assertEquals(56.0, $velocity['mean_days']);

        $this->assertCount(1, $velocity['still_monitoring']);
        $this->assertSame('Water Stress', $velocity['still_monitoring'][0]['keyword']);
        $this->assertEquals(15.0, $velocity['still_monitoring'][0]['days']);
        $this->assertEquals(15.0, $velocity['still_monitoring_median_days']);

        // The dimension filter narrows both to Ecology.
        $ecology = app(RatingHistoryAnalyticsService::class)->transitionVelocity(self::RANGE, $this->ecology->id);
        $this->assertSame(0, $ecology['count']);
        $this->assertNull($ecology['median_days']);
        $this->assertCount(1, $ecology['still_monitoring']);
    }

    public function test_reasoning_data_includes_history_analytics(): void
    {
        $this->buildHistory();
        Permission::firstOrCreate(
            ['name' => 'view-dashboard', 'guard_name' => 'web'],
            ['uuid' => (string) Uuid::uuid4(), 'category' => 'dashboard', 'description' => 'View dashboard']
        );
        $this->analyst->givePermissionTo('view-dashboard');

        $response = $this->actingAs($this->analyst)->getJson('/dashboard/reasoning-data');

        $response->assertStatus(200);
        $this->assertTrue($response->json('history_trend.enough'));
        $this->assertSame(1, $response->json('transition_velocity.count'));
    }
}
