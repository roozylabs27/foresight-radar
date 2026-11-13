<?php

namespace Tests\Feature\Journeys;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorThresholdEvent;
use App\Models\ProjectDecision;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCitation;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Signal;
use App\Models\Source;
use App\Models\StrategicResponse;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\KeepsLibraryScores;
use Tests\TestCase;

/**
 * Audit 2026-11-11 U7 (AUD-20, Scenario A): one organisation takes one strategic foresight exercise
 * from Step 1 to Step 9 through the HTTP routes the pages use, with a fake LLM only. The test checks
 * that each step unlocks the next, that every gate is logged, that evidence and links are carried
 * from step to step, that monitoring feeds back into review, and that a second exercise of the same
 * organisation sees none of this exercise's work.
 */
class ForesightExerciseJourneyTest extends TestCase
{
    use KeepsLibraryScores, RefreshDatabase;

    private User $coordinator;

    private User $viewer;

    /** @var array<string, DrivingForce> */
    private array $forces = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'BOD'])->givePermissionTo('view-scenario');
        $this->coordinator = User::factory()->create();
        $this->coordinator->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('bod');

        // The firm library the exercise draws on: rated driving forces.
        DimensionTaxonomy::align();
        $horizon = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        foreach (['Politic' => ['POLITICAL', 8, 8], 'Legislative' => ['LEGISLATIVE', 9, 7], 'Technology' => ['TECHNOLOGY', 8, 9], 'Purchasing Power' => ['CONSUMER', 7, 4]] as $keyword => [$code, $impact, $uncertainty]) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', $code)->value('id'), 'created_by' => $this->coordinator->id,
                'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
            ]);
            DrivingForceRating::create(['driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'impact_analysis' => $impact, 'uncertainty_analysis' => $uncertainty]);
            $this->forces[$keyword] = $force;
        }
    }

    private function as(?User $user = null)
    {
        return $this->actingAs($user ?? $this->coordinator);
    }

    private function link(ScenarioPlanningProject $project, string $keyword): ProjectDrivingForce
    {
        return ProjectDrivingForce::where('project_id', $project->id)->where('driving_force_id', $this->forces[$keyword]->id)->sole();
    }

    private function quadrant(ScenarioPlanningProject $project, string $position): ScenarioQuadrant
    {
        return Scenario::where('project_id', $project->id)->sole()->quadrants()->where('position', $position)->sole();
    }

    /** @return array<int, string> step statuses 1..9 as the overview shows them */
    private function steps(ScenarioPlanningProject $project): array
    {
        $steps = [];
        $this->as()->get(route('scenario-planning.show', $project))->assertOk()
            ->assertInertia(function ($page) use (&$steps) {
                $steps = collect($page->toArray()['props']['steps'])->pluck('status')->all();
            });

        return $steps;
    }

    private function acceptedSignal(string $keyword, string $title): Signal
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => "Report on {$title}", 'raw_content' => "{$title} is described in this report.",
            'content_hash' => hash('sha256', $title), 'created_by' => $this->coordinator->id,
        ]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'title' => $title, 'summary' => "Summary of {$title}.",
            'evidence_quote' => "{$title} is described in this report.", 'review_status' => 'ACCEPTED', 'reviewed_at' => now(), 'quote_verified' => true,
        ]);
        $signal->driving_forces()->attach($this->forces[$keyword]->id);

        return $signal;
    }

    public function test_one_organisation_completes_an_exercise_from_step_one_to_step_nine(): void
    {
        $llm = $this->fakeLlm();

        // Step 1 · Key focal issue (G1).
        $this->as()->post(route('scenario-planning.store'), [
            'name' => 'Elektrifikasi 2030', 'focal_question' => 'How will Indonesian vehicle electrification look in 2030?',
            'purpose' => 'Strategic proposal toward 2030.', 'target_year' => now()->year + 4,
        ])->assertSessionHasNoErrors();
        $project = ScenarioPlanningProject::sole();
        $this->assertSame(['in_progress', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked', 'locked'], $this->steps($project));
        $this->as()->post(route('scenario-planning.confirm', $project))->assertSessionHasNoErrors();

        // Step 2 · Driving forces from the firm library (G2), one predetermined.
        $this->as()->post(route('scenario-planning.forces.store', $project), ['forces' => array_map(fn ($force) => $force->uuid, array_values($this->forces))])->assertSessionHasNoErrors();
        $this->as()->put(route('scenario-planning.forces.update', [$project, $this->link($project, 'Purchasing Power')]), ['is_predetermined' => true])->assertSessionHasNoErrors();
        $this->as()->post(route('scenario-planning.forces.confirm', $project))->assertSessionHasNoErrors();
        $this->assertSame('done', $this->steps($project)[1]);

        // Step 3 · Scores agreed (G3) and two critical uncertainties approved as axes (G4).
        $this->keepLibraryScores($this->coordinator, $project);
        $this->as()->post(route('scenario-planning.assessment.confirm', $project))->assertSessionHasNoErrors();
        $uncertainty = fn (array $keywords, string $name, string $low, string $high) => $this->as()->post(route('scenario-planning.uncertainties.store', $project), [
            'name' => $name, 'description' => "{$name}.", 'forces' => array_map(fn ($k) => $this->link($project, $k)->uuid, $keywords),
            'low_pole_label' => $low, 'high_pole_label' => $high, 'selection_rationale' => 'Dampak dan ketidakpastian tertinggi.',
        ])->assertSessionHasNoErrors();
        $uncertainty(['Politic', 'Legislative'], 'Government Regulation', 'Unfavorable', 'Favorable');
        $uncertainty(['Technology'], 'Technology Development', 'Slow', 'Fast');
        $this->as()->post(route('scenario-planning.uncertainties.approve', [$project, CriticalUncertainty::where('name', 'Government Regulation')->sole()]), ['axis' => 'A'])->assertSessionHasNoErrors();
        $this->as()->post(route('scenario-planning.uncertainties.approve', [$project, CriticalUncertainty::where('name', 'Technology Development')->sole()]), ['axis' => 'B'])->assertSessionHasNoErrors();
        $this->assertSame('done', $this->steps($project)[2]);

        // Step 4 · Framework (G5).
        $this->as()->post(route('scenario-planning.framework.store', $project))->assertSessionHasNoErrors();
        $this->as()->post(route('scenario-planning.framework.approve', $project))->assertSessionHasNoErrors();
        $this->assertSame(['done', 'done', 'done', 'done', 'available', 'available', 'available', 'available', 'available'], $this->steps($project));

        // Step 5 · Two conditions per scenario.
        foreach (Scenario::POSITIONS as $position) {
            $this->as()->post(route('scenario-planning.conditions.store', [$project, $this->quadrant($project, $position)]), ['kind' => 'pole_expression', 'axis' => 'A', 'text' => "Regulasi {$position} berlaku."])->assertSessionHasNoErrors();
            $this->as()->post(route('scenario-planning.conditions.store', [$project, $this->quadrant($project, $position)]), ['kind' => 'pole_expression', 'axis' => 'B', 'text' => "Teknologi {$position} bergerak."])->assertSessionHasNoErrors();
        }
        $this->assertSame('done', $this->steps($project)[4]);

        // Step 6 · Narratives inside the exercise, citing accepted signals, then approved (G6).
        $politic = $this->acceptedSignal('Politic', 'Insentif TKDN diperluas');
        $this->acceptedSignal('Technology', 'Harga baterai LFP turun');
        $llm->respond(['quadrants' => array_map(fn ($position) => [
            'position' => $position, 'title' => "Masa depan {$position}", 'narrative' => "Narasi {$position} [S1].",
            'implications' => "Implikasi {$position}.", 'early_indicators' => ["Indikator {$position}"], 'cited_refs' => ['S1'],
        ], Scenario::POSITIONS)]);
        $this->as()->postJson(route('scenario-planning.narratives.generate', $project))->assertStatus(202);
        $this->assertSame(4, ScenarioCitation::where('signal_id', $politic->id)->count(), 'each narrative cites the accepted signal');
        foreach (Scenario::POSITIONS as $position) {
            $this->as()->postJson(route('scenario-planning.narratives.review', [$project, $this->quadrant($project, $position)]), ['status' => 'approved'])->assertOk();
        }
        $this->assertSame('done', $this->steps($project)[5]);

        // Step 7 · An opportunity and a challenge per scenario, linked to a force and a condition.
        foreach (Scenario::POSITIONS as $position) {
            $quadrant = $this->quadrant($project, $position);
            foreach (['opportunity', 'challenge'] as $type) {
                $this->as()->post(route('scenario-planning.implications.store', [$project, $quadrant]), [
                    'type' => $type, 'title' => "{$type} {$position}", 'forces' => [$this->link($project, 'Technology')->uuid],
                    'conditions' => [$quadrant->conditions()->value('uuid')],
                ])->assertSessionHasNoErrors();
            }
        }
        $this->assertSame('done', $this->steps($project)[6]);

        // Step 8 · A strategic response relying on an implication and a condition, approved (G7).
        $implication = ScenarioImplication::where('title', 'opportunity HH')->sole();
        $this->as()->post(route('scenario-planning.responses.store', $project), [
            'title' => 'Bangun ekosistem baterai', 'description' => 'Kemitraan pemasok baterai.', 'rationale' => 'Tangguh di tiga skenario.',
            'initiatives' => "Kemitraan pemasok\nPilot daur ulang", 'priority' => 1,
            'fit' => ['HH' => 'applies', 'HL' => 'applies', 'LH' => 'applies', 'LL' => 'partial'],
            'implications' => [$implication->uuid], 'conditions' => [$this->quadrant($project, 'HH')->conditions()->value('uuid')],
        ])->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();
        $this->as()->post(route('scenario-planning.responses.approve', [$project, $response]))->assertSessionHasNoErrors();
        $this->assertSame(['approved', 1, 1], [$response->fresh()->status, $response->implications()->count(), $response->conditions()->count()]);
        $this->assertSame('done', $this->steps($project)[7]);

        // Step 9 · Owned indicators with a direction in every scenario that together tell both axes
        // apart (PI-09); the first has a numeric threshold.
        $this->as()->post(route('scenario-planning.indicators.store', $project), [
            'name' => 'Pangsa penjualan xEV', 'uncertainty' => CriticalUncertainty::where('name', 'Technology Development')->value('uuid'),
            'metric' => 'Pangsa penjualan', 'unit' => '%', 'frequency' => 'quarterly', 'source_type' => 'manual',
            'threshold_operator' => 'gt', 'threshold_value' => 10, 'owner' => $this->coordinator->uuid,
            'directions' => ['HH' => ['direction' => 'rising'], 'LH' => ['direction' => 'rising'], 'HL' => ['direction' => 'falling'], 'LL' => ['direction' => 'falling']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $this->steps($project)[8], 'one axis told apart is not enough');
        $this->as()->post(route('scenario-planning.indicators.store', $project), [
            'name' => 'Biaya pendanaan proyek', 'metric' => 'Suku bunga kredit investasi', 'unit' => '%', 'frequency' => 'quarterly',
            'source_type' => 'manual', 'owner' => $this->coordinator->uuid,
            'directions' => ['HH' => ['direction' => 'falling'], 'HL' => ['direction' => 'falling'], 'LH' => ['direction' => 'rising'], 'LL' => ['direction' => 'rising']],
        ])->assertSessionHasNoErrors();
        $indicator = EarlyWarningIndicator::where('name', 'Pangsa penjualan xEV')->sole();
        $this->assertSame(array_fill(0, 9, 'done'), $this->steps($project), 'all nine steps done');

        // Monitoring feeds back: a crossing observation is decided on the server and flags review.
        $this->as()->post(route('scenario-planning.indicators.observations.store', [$project, $indicator]), [
            'observed_at' => now()->subWeek()->toDateString(), 'value_numeric' => 12, 'direction' => 'rising', 'signal' => $politic->uuid,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, IndicatorThresholdEvent::count());
        $this->assertNotNull(Scenario::where('project_id', $project->id)->sole()->stale_since);
        $this->assertNotNull($response->fresh()->stale_since);
        $this->as($this->viewer)->get(route('scenario-planning.indicators.index', $project))
            ->assertInertia(fn ($page) => $page->where('relevance.counted', 1)->where('relevance.leading', null)
                ->where('relevance.quadrants.0.reading', 'strengthening')->where('relevance.quadrants.1.reading', 'weakening'));

        // Every gate decision is logged once, in order, with the coordinator as actor.
        $this->assertSame(
            ['G1', 'G2', 'G3', 'G4', 'G4', 'G5', 'G6', 'G6', 'G6', 'G6', 'G7'],
            ProjectDecision::where('project_id', $project->id)->where('action', 'approved')->orderBy('id')->pluck('gate')->all()
        );
        $this->assertSame([$this->coordinator->id], ProjectDecision::where('project_id', $project->id)->distinct()->pluck('actor_id')->all());

        // The printable report carries the approved work.
        $this->as($this->viewer)->get(route('scenario-planning.report', $project))->assertOk();

        // A second exercise of the same organisation starts empty and cannot reach the first one's work.
        $this->as()->post(route('scenario-planning.store'), [
            'name' => 'Rantai pasok 2032', 'focal_question' => 'How will the battery supply chain look in 2032?',
            'purpose' => 'Sourcing strategy.', 'target_year' => now()->year + 6,
        ])->assertSessionHasNoErrors();
        $other = ScenarioPlanningProject::where('name', 'Rantai pasok 2032')->sole();
        $this->as()->get(route('scenario-planning.responses.index', $other))->assertInertia(fn ($page) => $page->has('responses', 0));
        $this->as()->get(route('scenario-planning.indicators.index', $other))->assertInertia(fn ($page) => $page->has('indicators', 0));
        $this->as()->post(route('scenario-planning.responses.approve', [$other, $response]))->assertNotFound();
        $this->as()->delete(route('scenario-planning.implications.destroy', [$other, $implication]))->assertNotFound();
        $this->as()->postJson(route('scenario-planning.narratives.review', [$other, $this->quadrant($project, 'HH')]), ['status' => 'draft'])->assertNotFound();
        $this->as()->post(route('scenario-planning.indicators.observations.store', [$other, $indicator]), ['observed_at' => now()->toDateString(), 'value_numeric' => 1])->assertNotFound();
        $this->assertSame(1, ScenarioImplication::where('title', 'opportunity HH')->count());
    }
}
