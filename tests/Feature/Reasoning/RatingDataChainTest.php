<?php

namespace Tests\Feature\Reasoning;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\DrivingForceRatingHistory;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\Signal;
use App\Models\Source;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\SignalExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Phase 1d: the radar, signal acceptance and rating history keep the scores
 * they are given instead of inventing or dropping them.
 */
class RatingDataChainTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;

    private Environment $environment;

    private StatusAction $monitoring;

    private TimeHorizon $shortTerm;

    private TimeHorizon $midTerm;

    private TimeHorizon $longTerm;

    protected function setUp(): void
    {
        parent::setUp();

        // The signal permissions are seeded by a migration.
        foreach (['view-foresight-radar', 'view-signal', 'create-signal', 'review-signal'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid4(), 'category' => 'general', 'description' => $name]
            );
        }

        $this->analyst = User::factory()->create();
        $this->analyst->givePermissionTo(['view-foresight-radar', 'view-signal', 'create-signal', 'review-signal']);

        $this->environment = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $this->monitoring = StatusAction::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Monitoring', 'symbol' => 'triangle', 'code' => '∆']);

        foreach (['High', 'Medium', 'Low'] as $name) {
            Priority::create(['uuid' => (string) Uuid::uuid4(), 'name' => $name, 'color' => strtolower($name)]);
        }

        $this->shortTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Short Term', 'code' => 'ST']);
        $this->midTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        $this->longTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Long Term', 'code' => 'LT']);
    }

    private function dimension(string $name): Dimension
    {
        return Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => $name, 'environment_id' => $this->environment->id]);
    }

    private function approvedForce(Dimension $dimension, TimeHorizon $horizon, ?int $impact): DrivingForceRating
    {
        $df = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => "{$dimension->name} {$horizon->code} force",
            'description' => 'Driving force used to check the radar layout.',
            'status' => 'APPROVED',
            'dimension_id' => $dimension->id,
            'pic' => $this->analyst->id,
            'created_by' => $this->analyst->id,
        ]);

        return DrivingForceRating::create([
            'driving_force_id' => $df->id,
            'time_horizon_id' => $horizon->id,
            'status_action_id' => $this->monitoring->id,
            'impact_analysis' => $impact,
            'uncertainty_analysis' => 5,
        ]);
    }

    public function test_radar_position_follows_horizon_and_impact_and_is_stable_between_reloads(): void
    {
        // A gap in dimension ids: the radar must not assume ids start at 1 and run in order.
        $this->dimension('Removed')->delete();
        $economy = $this->dimension('Economy');
        $ecology = $this->dimension('Ecology');

        $this->approvedForce($economy, $this->shortTerm, 10);
        $this->approvedForce($ecology, $this->longTerm, 1);
        $this->approvedForce($economy, $this->midTerm, null);

        $first = $this->actingAs($this->analyst)->getJson('/visualization/foresight-radar/get-data');
        $first->assertStatus(200);
        $second = $this->actingAs($this->analyst)->getJson('/visualization/foresight-radar/get-data');

        $this->assertSame($first->json(), $second->json());

        $values = collect($first->json('data') ?? $first->json())->pluck('value', 'keyword');
        // Short Term band is 2–4: impact 10 sits at the inner edge
        $this->assertEquals([2.0, 0], $values['Economy ST force']);
        // Long Term band is 7–8: impact 1 sits at the outer edge
        $this->assertEquals([0, 8.0], $values['Ecology LT force']);
        // Mid Term band is 5–6: an unrated impact counts as 5
        $this->assertEquals([5.56, 0], $values['Economy MT force']);
    }

    public function test_mid_term_cues_are_detected_as_mid_term(): void
    {
        $service = new class extends SignalExtractionService
        {
            public function horizon(string $text): ?TimeHorizon
            {
                return $this->detectTimeHorizon($text, TimeHorizon::all());
            }
        };

        $this->assertSame('Mid Term', $service->horizon('Suppliers expect a steady shift in sourcing patterns.')?->name);
        $this->assertSame('Short Term', $service->horizon('The rule takes effect within months.')?->name);
        $this->assertSame('Long Term', $service->horizon('A transformational change expected by 2035.')?->name);
    }

    public function test_accept_create_carries_signal_scores_into_a_rating(): void
    {
        $technology = $this->dimension('Technology');
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Robotics Report',
            'raw_content' => 'Humanoid robots enter automotive assembly lines.',
            'content_hash' => hash('sha256', 'Humanoid robots enter automotive assembly lines.'),
            'created_by' => $this->analyst->id,
        ]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'dimension_id' => $technology->id,
            'suggested_time_horizon_id' => $this->midTerm->id,
            'preliminary_impact' => 8,
            'preliminary_uncertainty' => 7,
            'title' => 'Autonomous Factory Robotics Integration',
            'summary' => 'Humanoid robots enter automotive assembly lines at scale.',
            'evidence_quote' => 'Humanoid robots enter automotive assembly lines at scale.',
            'review_status' => 'PENDING',
            'confidence_score' => 0.85,
        ]);

        $response = $this->actingAs($this->analyst)->postJson("/signals/{$signal->uuid}/review", [
            'action' => 'accept_create',
            'preliminary_impact' => 9,
        ]);

        $response->assertStatus(200);
        $rating = DrivingForce::find($signal->fresh()->created_driving_force_id)->rating;
        $this->assertNotNull($rating);
        $this->assertSame($this->midTerm->id, $rating->time_horizon_id);
        $this->assertSame(9, $rating->impact_analysis);
        $this->assertSame(7, $rating->uncertainty_analysis);
        $this->assertSame('High', $rating->priority->name);
        $this->assertNull($rating->status_action_id);
    }

    public function test_rating_history_records_creation_and_each_score_change(): void
    {
        $this->actingAs($this->analyst);
        $rating = $this->approvedForce($this->dimension('Economy'), $this->shortTerm, 6);

        $rating->update(['impact_analysis' => 8]);
        $rating->update(['impact_analysis' => 8]);
        $rating->touch();
        $rating->update(['uncertainty_analysis' => 9, 'time_horizon_id' => $this->longTerm->id]);

        $history = $rating->histories()->get();
        $this->assertSame(['created', 'updated', 'updated'], $history->pluck('event')->all());
        $this->assertSame([6, 8, 8], $history->pluck('impact_analysis')->all());
        $this->assertSame([5, 5, 9], $history->pluck('uncertainty_analysis')->all());
        $this->assertSame($this->longTerm->id, $history->last()->time_horizon_id);
        $this->assertSame($this->analyst->id, $history->last()->changed_by);
        $this->assertSame(3, DrivingForceRatingHistory::where('driving_force_id', $rating->driving_force_id)->count());
    }
}
