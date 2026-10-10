<?php

namespace Tests\Feature\Reliability;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\ForesightReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForesightReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Dimension $dimension;
    private TimeHorizon $timeHorizon;
    private StatusAction $statusAction;
    private Priority $priority;
    private ForesightReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $env = Environment::create([
            'uuid' => fake()->uuid(),
            'name' => 'Technological',
        ]);

        $this->dimension = Dimension::create([
            'uuid' => fake()->uuid(),
            'name' => 'Artificial Intelligence',
            'environment_id' => $env->id,
        ]);

        $this->timeHorizon = TimeHorizon::create([
            'uuid' => fake()->uuid(),
            'name' => 'Near Term',
            'code' => 'NT',
        ]);

        $this->statusAction = StatusAction::create([
            'uuid' => fake()->uuid(),
            'name' => 'Monitor',
            'symbol' => 'M',
            'code' => 'ACT-M',
        ]);

        $this->priority = Priority::create([
            'uuid' => fake()->uuid(),
            'name' => 'High',
            'color' => '#ff0000',
        ]);

        $this->service = app(ForesightReportingService::class);
    }

    public function test_resolve_date_range_parses_valid_dates(): void
    {
        $dates = ['2026-05-01', '2026-05-31'];
        $resolved = $this->service->resolveDateRange($dates);

        $this->assertEquals('2026-05-01 00:00:00', $resolved[0]);
        $this->assertEquals('2026-05-31 23:59:59', $resolved[1]);
    }

    public function test_resolve_date_range_falls_back_to_current_month(): void
    {
        $resolved = $this->service->resolveDateRange(null);

        $this->assertEquals(Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'), $resolved[0]);
        $this->assertEquals(Carbon::now()->endOfMonth()->format('Y-m-d 23:59:59'), $resolved[1]);
    }

    public function test_radar_dataset_excludes_pending_and_unrated_forces(): void
    {
        // Approved force with status action
        $approvedForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Approved Tech',
            'description' => 'Valid approved foresight',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'APPROVED',
        ]);

        DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $approvedForce->id,
            'time_horizon_id' => $this->timeHorizon->id,
            'status_action_id' => $this->statusAction->id,
            'priority_id' => $this->priority->id,
            'impact_analysis' => 9,
            'uncertainty_analysis' => 8,
        ]);

        // Pending force (should NOT be included)
        $pendingForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Pending Tech',
            'description' => 'Pending foresight item',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'PENDING',
        ]);

        DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $pendingForce->id,
            'time_horizon_id' => $this->timeHorizon->id,
            'status_action_id' => $this->statusAction->id,
            'priority_id' => $this->priority->id,
            'impact_analysis' => 5,
            'uncertainty_analysis' => 5,
        ]);

        // Approved force without status action (should NOT be included)
        $unratedForce = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Unrated Tech',
            'description' => 'Missing action',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'APPROVED',
        ]);

        DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $unratedForce->id,
            'time_horizon_id' => $this->timeHorizon->id,
            'status_action_id' => null,
            'priority_id' => null,
            'impact_analysis' => 5,
            'uncertainty_analysis' => 5,
        ]);

        $dataset = $this->service->getRadarDataset();
        $this->assertCount(1, $dataset);
        $this->assertEquals('Approved Tech', $dataset[0]->driving_force->keyword);
    }

    public function test_registered_list_query_applies_dimension_and_time_horizon_filters(): void
    {
        $otherHorizon = TimeHorizon::create([
            'uuid' => fake()->uuid(),
            'name' => 'Far Term',
            'code' => 'FT',
        ]);

        $force1 = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Near Term Signal',
            'description' => 'Expected near term',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'APPROVED',
        ]);

        DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $force1->id,
            'time_horizon_id' => $this->timeHorizon->id,
            'status_action_id' => $this->statusAction->id,
            'priority_id' => $this->priority->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
        ]);

        $force2 = DrivingForce::create([
            'uuid' => fake()->uuid(),
            'keyword' => 'Far Term Signal',
            'description' => 'Expected far term',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'status' => 'APPROVED',
        ]);

        DrivingForceRating::create([
            'uuid' => fake()->uuid(),
            'driving_force_id' => $force2->id,
            'time_horizon_id' => $otherHorizon->id,
            'status_action_id' => $this->statusAction->id,
            'priority_id' => $this->priority->id,
            'impact_analysis' => 6,
            'uncertainty_analysis' => 6,
        ]);

        $allResults = $this->service->getRegisteredListQuery()->get();
        $this->assertCount(2, $allResults);

        $filteredResults = $this->service->getRegisteredListQuery(null, null, $otherHorizon->id)->get();
        $this->assertCount(1, $filteredResults);
        $this->assertEquals('Far Term Signal', $filteredResults[0]->driving_force->keyword);
    }
}
