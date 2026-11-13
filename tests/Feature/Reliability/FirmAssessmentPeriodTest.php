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
use App\Services\ReasoningEngineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PI-07 / PAD-1: the FIRM reporting period means "assessed in the period". A rating belongs to the
 * period in which its latest assessment (a change of a scored field) happened, not the period in which
 * the rating row was created. Dashboard, Radar, Prioritizing and Registered List share the filter.
 */
class FirmAssessmentPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Dimension $dimension;

    private TimeHorizon $horizon;

    private StatusAction $action;

    private Priority $priority;

    private ForesightReportingService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $environment = Environment::create(['uuid' => fake()->uuid(), 'name' => 'Technological']);
        $this->dimension = Dimension::create(['uuid' => fake()->uuid(), 'name' => 'Artificial Intelligence', 'environment_id' => $environment->id]);
        $this->horizon = TimeHorizon::create(['uuid' => fake()->uuid(), 'name' => 'Near Term', 'code' => 'NT']);
        $this->action = StatusAction::create(['uuid' => fake()->uuid(), 'name' => 'Monitor', 'symbol' => 'M', 'code' => 'ACT-M']);
        $this->priority = Priority::create(['uuid' => fake()->uuid(), 'name' => 'High', 'color' => '#ff0000']);
        $this->reports = app(ForesightReportingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An approved force whose rating is created (first assessed) at `$at`. */
    private function ratedAt(string $keyword, string $at): DrivingForceRating
    {
        Carbon::setTestNow(Carbon::parse($at));
        $force = DrivingForce::create([
            'uuid' => fake()->uuid(), 'keyword' => $keyword, 'description' => "{$keyword}.", 'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id, 'pic' => $this->user->id, 'status' => 'APPROVED',
        ]);

        $rating = DrivingForceRating::create([
            'uuid' => fake()->uuid(), 'driving_force_id' => $force->id, 'time_horizon_id' => $this->horizon->id,
            'status_action_id' => $this->action->id, 'priority_id' => $this->priority->id, 'impact_analysis' => 7, 'uncertainty_analysis' => 6,
        ]);
        Carbon::setTestNow();

        return $rating;
    }

    /** Keywords of the FIRM radar for a period. */
    private function radar(string $from, string $to): array
    {
        return $this->reports->baseApprovedRatingQuery([$from, $to])->with('driving_force')->get()
            ->map(fn ($rating) => $rating->driving_force->keyword)->sort()->values()->all();
    }

    public function test_a_rating_assessed_inside_the_period_is_included_and_outside_is_excluded(): void
    {
        $this->ratedAt('January', '2026-01-15 10:00:00');
        $this->ratedAt('March', '2026-03-10 10:00:00');

        $this->assertSame(['January'], $this->radar('2026-01-01', '2026-01-31'));
        $this->assertSame(['March'], $this->radar('2026-03-01', '2026-03-31'));
        $this->assertSame([], $this->radar('2026-02-01', '2026-02-28'));
    }

    public function test_a_reassessment_moves_the_rating_to_the_period_of_the_latest_assessment(): void
    {
        $rating = $this->ratedAt('Reassessed', '2026-01-15 10:00:00');

        Carbon::setTestNow('2026-03-20 09:00:00');
        $rating->update(['impact_analysis' => 9]);
        Carbon::setTestNow();

        // The creation month no longer decides: the latest assessment does.
        $this->assertSame([], $this->radar('2026-01-01', '2026-01-31'));
        $this->assertSame(['Reassessed'], $this->radar('2026-03-01', '2026-03-31'));
        $this->assertSame('2026-03-20 09:00:00', $rating->fresh()->assessed_at->format('Y-m-d H:i:s'));
    }

    public function test_a_save_that_changes_no_score_is_not_an_assessment(): void
    {
        $rating = $this->ratedAt('Touched', '2026-01-15 10:00:00');

        Carbon::setTestNow('2026-03-20 09:00:00');
        $rating->touch();
        $rating->update(['impact_analysis' => 7]);
        Carbon::setTestNow();

        $this->assertSame('2026-01-15 10:00:00', $rating->fresh()->assessed_at->format('Y-m-d H:i:s'));
        $this->assertSame(['Touched'], $this->radar('2026-01-01', '2026-01-31'));
    }

    public function test_period_boundaries_are_whole_days_in_the_application_timezone(): void
    {
        $this->ratedAt('First second', '2026-03-01 00:00:00');
        $this->ratedAt('Last second', '2026-03-31 23:59:59');
        $this->ratedAt('Next month', '2026-04-01 00:00:00');
        $this->ratedAt('Previous month', '2026-02-28 23:59:59');

        $this->assertSame(['First second', 'Last second'], $this->radar('2026-03-01', '2026-03-31'));
    }

    public function test_every_firm_report_uses_the_same_assessment_period(): void
    {
        $rating = $this->ratedAt('Reassessed', '2026-01-15 10:00:00');
        $this->ratedAt('January only', '2026-01-20 10:00:00');
        Carbon::setTestNow('2026-03-20 09:00:00');
        $rating->update(['uncertainty_analysis' => 9]);
        Carbon::setTestNow();
        $march = ['2026-03-01', '2026-03-31'];

        $this->assertStringContainsString('Reassessed', json_encode($this->reports->getRadarDataset($march)));
        $this->assertStringNotContainsString('January only', json_encode($this->reports->getRadarDataset($march)));
        $this->assertStringContainsString('Reassessed', json_encode($this->reports->getPrioritizingDataset($march)));
        $this->assertStringNotContainsString('January only', json_encode($this->reports->getPrioritizingDataset($march)));
        $this->assertSame(['Reassessed'], $this->reports->getRegisteredListQuery($march)->get()->map(fn ($row) => $row->driving_force->keyword)->all());

        // The dashboard trend counts the rating in the month it was assessed.
        $trend = collect(app(ReasoningEngineService::class)->calculateEdaTrend(['2026-01-01', '2026-03-31'])['data'])->keyBy('period');
        $this->assertSame(1, $trend['2026-01']['total']);
        $this->assertSame(1, $trend['2026-03']['total']);
    }

    /**
     * Ratings older than the assessment column take their latest history event; a rating with no
     * history at all falls back to its creation time (stated, never guessed from updated_at).
     */
    public function test_the_backfill_uses_the_history_and_falls_back_to_the_creation_time(): void
    {
        $withHistory = $this->ratedAt('With history', '2026-01-15 10:00:00');
        $withoutHistory = $this->ratedAt('Without history', '2026-02-10 10:00:00');
        DB::table('driving_force_rating_histories')->insert([
            'driving_force_rating_id' => $withHistory->id, 'driving_force_id' => $withHistory->driving_force_id,
            'event' => 'updated', 'impact_analysis' => 9, 'changed_at' => '2026-03-05 08:00:00',
        ]);
        DB::table('driving_force_rating_histories')->where('driving_force_rating_id', $withoutHistory->id)->delete();
        DB::table('driving_force_ratings')->update(['assessed_at' => null, 'updated_at' => '2026-06-01 00:00:00']);

        DrivingForceRating::backfillAssessedAt();

        $this->assertSame('2026-03-05 08:00:00', $withHistory->fresh()->assessed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-10 10:00:00', $withoutHistory->fresh()->assessed_at->format('Y-m-d H:i:s'));
    }
}
