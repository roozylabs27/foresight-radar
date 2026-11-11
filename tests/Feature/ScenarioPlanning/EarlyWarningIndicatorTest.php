<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\AiGenerationRun;
use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\ProjectDrivingForce;
use App\Models\ProjectParticipant;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\EarlyWarningIndicatorService;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R1.9: step 9 keeps an early warning indicator register per exercise
 * (what to monitor, metric, baseline, frequency, owner, source type,
 * threshold) with the expected direction in each quadrant. Indicator strings
 * from narratives become proposals. Step 9 is done when an accepted indicator
 * has a direction in all four quadrants.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 9.
 */
class EarlyWarningIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private ScenarioPlanningProject $project;

    /** @var array<string, DrivingForce> */
    private array $forces = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'BOD'])->givePermissionTo('view-scenario');
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('bod');

        DimensionTaxonomy::align();
        $horizon = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        foreach (['Politic' => ['POLITICAL', 8, 8], 'Legislative' => ['LEGISLATIVE', 9, 7], 'Technology' => ['TECHNOLOGY', 8, 9], 'Purchasing Power' => ['CONSUMER', 7, 4]] as $keyword => [$code, $impact, $uncertainty]) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', $code)->value('id'), 'created_by' => $this->manager->id,
                'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
            ]);
            DrivingForceRating::create(['driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'impact_analysis' => $impact, 'uncertainty_analysis' => $uncertainty]);
            $this->forces[$keyword] = $force;
        }

        $this->project = $this->exercise();
    }

    private function exercise(bool $agreeScores = true): ScenarioPlanningProject
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Elektrifikasi 2030', 'focal_question' => 'How Indonesia vehicle electrification look like in 2030?',
            'purpose' => 'Strategic proposal toward 2030.', 'target_year' => now()->year + 4,
        ]);
        $project = ScenarioPlanningProject::latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project));
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $project), ['forces' => array_map(fn ($f) => $f->uuid, array_values($this->forces))]);
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, 'Purchasing Power')]), ['is_predetermined' => true]);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        if ($agreeScores) {
            $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project));
        }

        return $project->fresh();
    }

    private function link(ScenarioPlanningProject $project, string $keyword): ProjectDrivingForce
    {
        return ProjectDrivingForce::where('project_id', $project->id)->where('driving_force_id', $this->forces[$keyword]->id)->sole();
    }

    private function payload(array $keywords, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Government Regulation',
            'description' => 'Arah kebijakan dan regulasi elektrifikasi.',
            'forces' => array_map(fn ($k) => $this->link($this->project, $k)->uuid, $keywords),
            'low_pole_label' => 'Unfavorable',
            'high_pole_label' => 'Favorable',
            'selection_rationale' => 'Dampak dan ketidakpastian tertinggi; menentukan insentif dan TKDN.',
        ];
    }

    private function propose(array $keywords, array $overrides = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.uncertainties.store', $this->project), $this->payload($keywords, $overrides));
    }

    private function approve(CriticalUncertainty $uncertainty, string $axis, bool $acknowledge = false)
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.uncertainties.approve', [$this->project, $uncertainty]), ['axis' => $axis, 'acknowledge_dependency' => $acknowledge]);
    }

    private function twoProposals(): array
    {
        $this->propose(['Politic', 'Legislative'])->assertSessionHasNoErrors();
        $this->propose(['Technology'], ['name' => 'Technology Development', 'low_pole_label' => 'Slow', 'high_pole_label' => 'Fast'])->assertSessionHasNoErrors();

        return [CriticalUncertainty::where('name', 'Government Regulation')->sole(), CriticalUncertainty::where('name', 'Technology Development')->sole()];
    }

    /** Two approved uncertainties: Government Regulation (A) and Technology Development (B). */
    private function approveAxes(): array
    {
        [$regulation, $technology] = $this->twoProposals();
        $this->approve($regulation, 'A')->assertSessionHasNoErrors();
        $this->approve($technology, 'B')->assertSessionHasNoErrors();

        return [$regulation->fresh(), $technology->fresh()];
    }

    private function createFramework(array $data = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.framework.store', $this->project), $data);
    }

    private function framework(): Scenario
    {
        return Scenario::where('project_id', $this->project->id)->sole();
    }

    private function quadrant(string $position): ScenarioQuadrant
    {
        return $this->framework()->quadrants()->where('position', $position)->sole();
    }

    private function poles(array $overrides = []): array
    {
        return $overrides + [
            'axis_a_low_label' => 'Unfavorable',
            'axis_a_high_label' => 'Favorable',
            'axis_b_low_label' => 'Slow',
            'axis_b_high_label' => 'Fast',
        ];
    }

    private function approveFramework()
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.framework.approve', $this->project));
    }

    private function readyFramework(): Scenario
    {
        $this->approveAxes();
        $this->createFramework();
        $this->approveFramework()->assertSessionHasNoErrors();

        return $this->framework();
    }

    /**
     * Deck slide 25 ("xEV technology adoption grows steadily"): adoption rises where technology is fast.
     * Axis A is Government Regulation and axis B Technology Development, so FAVOUR is HH (favourable · fast),
     * DICHOTOMY LH (unfavourable · fast), STAGNATION HL (favourable · slow) and CHALLENGING LL (unfavourable · slow).
     */
    private function indicatorPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'xEV adoption growth',
            'description' => 'Pertumbuhan penjualan kendaraan listrik per tahun.',
            'uncertainty' => CriticalUncertainty::where('name', 'Technology Development')->value('uuid'),
            'metric' => 'Pangsa penjualan xEV', 'unit' => '%', 'baseline_value' => 3.5, 'baseline_date' => '2026-06-30',
            'frequency' => 'quarterly', 'source_type' => 'manual', 'threshold_rule' => 'Di atas 10% sebelum 2028',
            'directions' => [
                'HH' => ['direction' => 'rising'], 'LH' => ['direction' => 'rising'],
                'HL' => ['direction' => 'falling'], 'LL' => ['direction' => 'falling', 'note' => 'Lambat'],
            ],
        ];
    }

    private function storeIndicator(array $overrides = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.indicators.store', $this->project), $this->indicatorPayload($overrides));
    }

    public function test_indicators_wait_for_an_approved_framework(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->storeIndicator()->assertSessionHasErrors('indicators');
        $this->assertSame(0, EarlyWarningIndicator::count());
    }

    public function test_the_deck_indicator_has_a_direction_per_quadrant_and_its_monitoring_details(): void
    {
        $this->readyFramework();

        $this->storeIndicator(['owner' => $this->manager->uuid])->assertSessionHasNoErrors();

        $indicator = EarlyWarningIndicator::sole();
        $this->assertSame(['quarterly', 'manual', 'accepted', 'human', 'active'], [$indicator->frequency, $indicator->source_type, $indicator->review_status, $indicator->origin, $indicator->status]);
        $this->assertSame(CriticalUncertainty::where('name', 'Technology Development')->value('id'), $indicator->critical_uncertainty_id);
        $this->assertSame([$this->manager->id, '%', '2026-06-30'], [$indicator->owner_id, $indicator->unit, $indicator->baseline_date->toDateString()]);
        $directions = $indicator->directions()->with('quadrant')->get()->mapWithKeys(fn ($row) => [$row->quadrant->position => $row->expected_direction])->all();
        $this->assertEqualsCanonicalizing(['HH' => 'rising', 'LH' => 'rising', 'HL' => 'falling', 'LL' => 'falling'], $directions);
    }

    public function test_validation_rules(): void
    {
        $this->readyFramework();
        $other = $this->exercise();
        $foreign = ProjectDrivingForce::where('project_id', $other->id)->firstOrFail();

        $this->storeIndicator(['name' => str_repeat('a', 161)])->assertSessionHasErrors('name');
        $this->storeIndicator(['frequency' => 'daily'])->assertSessionHasErrors('frequency');
        $this->storeIndicator(['source_type' => 'satellite'])->assertSessionHasErrors('source_type');
        $this->storeIndicator(['directions' => ['HH' => ['direction' => 'up']]])->assertSessionHasErrors('directions.HH.direction');
        $this->storeIndicator(['threshold_rule' => str_repeat('a', 501)])->assertSessionHasErrors('threshold_rule');
        $this->storeIndicator(['uncertainty' => (string) Uuid::uuid4()])->assertSessionHasErrors('uncertainty');
        $this->storeIndicator(['force' => $foreign->uuid])->assertSessionHasErrors('force');
        $this->assertSame(0, EarlyWarningIndicator::count());
    }

    public function test_an_indicator_with_one_direction_everywhere_is_flagged_as_not_discriminating(): void
    {
        $this->readyFramework();
        $same = array_fill_keys(Scenario::POSITIONS, ['direction' => 'rising']);
        $this->storeIndicator(['name' => 'Harga minyak', 'directions' => $same]);
        $this->storeIndicator();

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Indicators')
                ->where('indicators.0.name', 'Harga minyak')
                ->where('indicators.0.discriminates', false)
                ->where('indicators.1.discriminates', true)
                ->where('indicators.1.source_type', 'manual')
                ->has('quadrants', 4)
                ->where('canManage', false));
    }

    public function test_narrative_indicator_strings_become_proposals_once_and_stay_visible(): void
    {
        $this->readyFramework();
        $this->quadrant('HH')->update(['early_indicators' => ['Penjualan EV naik', 'Tarif listrik turun']]);
        $this->quadrant('LL')->update(['early_indicators' => ['penjualan EV naik ']]);

        $this->assertSame(2, EarlyWarningIndicatorService::importNarrativeIndicators());
        $this->assertSame(0, EarlyWarningIndicatorService::importNarrativeIndicators());

        $proposal = EarlyWarningIndicator::where('name', 'Penjualan EV naik')->sole();
        $this->assertSame(['proposed', 'migrated'], [$proposal->review_status, $proposal->origin]);

        $this->actingAs($this->manager)->put(route('scenario-planning.indicators.update', [$this->project, $proposal]), $this->indicatorPayload(['name' => 'Penjualan EV naik']))
            ->assertSessionHasNoErrors();
        $this->assertSame('accepted', $proposal->fresh()->review_status);

        $this->actingAs($this->manager)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('quadrants.0.position', 'HH')->where('quadrants.0.narrative_indicators', ['Penjualan EV naik', 'Tarif listrik turun']));
    }

    public function test_indicators_can_be_retired_deleted_and_stay_in_their_exercise(): void
    {
        $this->readyFramework();
        $this->storeIndicator();
        $indicator = EarlyWarningIndicator::sole();

        $this->actingAs($this->manager)->put(route('scenario-planning.indicators.update', [$this->project, $indicator]), $this->indicatorPayload(['status' => 'retired']))->assertSessionHasNoErrors();
        $this->assertSame('retired', $indicator->fresh()->status);

        $other = $this->exercise();
        $this->actingAs($this->manager)->delete(route('scenario-planning.indicators.destroy', [$other, $indicator]))->assertNotFound();
        $this->actingAs($this->viewer)->delete(route('scenario-planning.indicators.destroy', [$this->project, $indicator]))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('scenario-planning.indicators.destroy', [$this->project, $indicator]))->assertSessionHasNoErrors();
        $this->assertSame(0, EarlyWarningIndicator::count());
    }

    public function test_step_nine_is_done_with_an_accepted_indicator_rated_in_all_quadrants(): void
    {
        $this->readyFramework();
        $step = fn () => $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project));

        $step()->assertInertia(fn ($page) => $page->where('steps.8.status', 'available'));
        $this->storeIndicator(['directions' => ['HH' => ['direction' => 'rising']]]);
        $step()->assertInertia(fn ($page) => $page->where('steps.8.status', 'in_progress'));
        $this->storeIndicator(['name' => 'Lengkap']);
        $step()->assertInertia(fn ($page) => $page->where('steps.8.status', 'done'));
    }

    private function signal(string $title, string $status = 'ACCEPTED'): Signal
    {
        $raw = "{$title} raw text";
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => "{$title} source", 'url' => 'https://example.test/'.md5($title),
            'raw_content' => $raw, 'content_hash' => hash('sha256', $raw), 'created_by' => $this->manager->id,
        ]);

        return Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'dimension_id' => Dimension::where('code', 'TECHNOLOGY')->value('id'),
            'title' => $title, 'summary' => "{$title}.", 'evidence_quote' => "{$title}.", 'confidence_score' => 0.9, 'review_status' => $status,
        ]);
    }

    private function observe(User $user, EarlyWarningIndicator $indicator, array $values)
    {
        return $this->actingAs($user)->post(route('scenario-planning.indicators.observations.store', [$this->project, $indicator]), $values);
    }

    /** Roadmap R2.3 (observation log): a contributor records an observation, optionally evidenced by an accepted signal. */
    public function test_a_contributor_records_observations_with_an_optional_accepted_signal(): void
    {
        $this->readyFramework();
        $this->storeIndicator();
        $indicator = EarlyWarningIndicator::sole();
        $accepted = $this->signal('xEV sales up 40%');
        $this->signal('Unreviewed rumour', 'PENDING');

        $this->observe($this->viewer, $indicator, [
            'observed_at' => '2026-10-31', 'value_numeric' => 6.2, 'direction' => 'rising',
            'note' => 'Data Gaikindo Q3.', 'signal' => $accepted->uuid,
        ])->assertSessionHasNoErrors();
        $this->observe($this->manager, $indicator, ['observed_at' => '2026-07-31', 'value_text' => 'Stagnan'])->assertSessionHasNoErrors();

        $first = IndicatorObservation::where('note', 'Data Gaikindo Q3.')->sole();
        $this->assertSame([6.2, 'rising', $accepted->id, $this->viewer->id, false], [$first->value_numeric, $first->direction, $first->signal_id, $first->recorded_by, $first->crossed_threshold]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.observation_count', 2)
                ->where('indicators.0.observations.0.observed_at', '2026-10-31')
                ->where('indicators.0.observations.0.value_numeric', 6.2)
                ->where('indicators.0.observations.0.signal.title', 'xEV sales up 40%')
                ->where('indicators.0.observations.0.recorded_by', $this->viewer->name)
                ->where('indicators.0.observations.1.value_text', 'Stagnan')
                ->has('signals', 1)
                ->where('signals.0.title', 'xEV sales up 40%')
                ->where('canRecord', true));
    }

    public function test_observation_rules(): void
    {
        $this->readyFramework();
        $this->storeIndicator();
        $indicator = EarlyWarningIndicator::sole();
        $pending = $this->signal('Unreviewed rumour', 'PENDING');

        $this->observe($this->viewer, $indicator, ['observed_at' => '2026-10-31'])->assertSessionHasErrors('value_numeric');
        $this->observe($this->viewer, $indicator, ['observed_at' => now()->addDays(3)->toDateString(), 'value_text' => 'x'])->assertSessionHasErrors('observed_at');
        $this->observe($this->viewer, $indicator, ['observed_at' => '2026-10-31', 'value_text' => 'x', 'signal' => $pending->uuid])->assertSessionHasErrors('signal');
        $this->observe($this->viewer, $indicator, ['observed_at' => '2026-10-31', 'value_text' => 'x', 'direction' => 'sideways'])->assertSessionHasErrors('direction');
        $this->assertSame(0, IndicatorObservation::count());

        $this->observe($this->viewer, $indicator, ['observed_at' => '2026-10-31', 'value_numeric' => 5])->assertSessionHasNoErrors();
        $observation = IndicatorObservation::sole();
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->actingAs($colleague)->delete(route('scenario-planning.indicators.observations.destroy', [$this->project, $indicator, $observation]))->assertForbidden();

        ProjectParticipant::create(['project_id' => $this->project->id, 'tenant_id' => $this->project->tenant_id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
        $this->observe($this->viewer, $indicator, ['observed_at' => '2026-10-31', 'value_numeric' => 7])->assertForbidden();
        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))->assertInertia(fn ($page) => $page->where('canRecord', false));

        $this->actingAs($this->manager)->delete(route('scenario-planning.indicators.observations.destroy', [$this->project, $indicator, $observation]))->assertSessionHasNoErrors();
        $this->assertSame(0, IndicatorObservation::count());
    }

    /**
     * Roadmap R2.3 (relevance view): an observation's direction supports every quadrant expecting
     * it and contradicts the others; qualitative or unset expectations, observations without a
     * direction and retired indicators are not counted.
     */
    public function test_observations_show_which_scenario_is_becoming_relevant(): void
    {
        $this->readyFramework();
        $this->storeIndicator(); // HH, LH rising; HL, LL falling
        $adoption = EarlyWarningIndicator::sole();
        $this->storeIndicator(['name' => 'Charging stations', 'directions' => [
            'HH' => ['direction' => 'qualitative'], 'LH' => ['direction' => 'rising'], 'LL' => ['direction' => 'falling'],
        ]]);
        $charging = EarlyWarningIndicator::where('name', 'Charging stations')->sole();
        $this->storeIndicator(['name' => 'Retired one', 'status' => 'retired']);
        $retired = EarlyWarningIndicator::where('name', 'Retired one')->sole();

        foreach ([[$adoption, 'rising'], [$adoption, 'rising'], [$adoption, 'falling'], [$adoption, null], [$charging, 'rising'], [$retired, 'rising']] as [$indicator, $direction]) {
            $this->observe($this->manager, $indicator, array_filter(['observed_at' => '2026-10-01', 'value_numeric' => 1, 'direction' => $direction]))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('relevance.observations', 4)
                ->where('relevance.quadrants.0.position', 'HH')
                ->where('relevance.quadrants.0.supporting', 2)
                ->where('relevance.quadrants.0.contradicting', 1)
                ->where('relevance.quadrants.1.position', 'HL')
                ->where('relevance.quadrants.1.supporting', 1)
                ->where('relevance.quadrants.1.contradicting', 2)
                ->where('relevance.quadrants.2.position', 'LH')
                ->where('relevance.quadrants.2.supporting', 3)
                ->where('relevance.quadrants.2.contradicting', 1)
                ->where('relevance.quadrants.3.position', 'LL')
                ->where('relevance.quadrants.3.supporting', 1)
                ->where('relevance.quadrants.3.contradicting', 3)
                ->where('relevance.leading', 'LH'));
    }

    public function test_no_relevance_lead_without_directional_observations(): void
    {
        $this->readyFramework();
        $this->storeIndicator();
        $this->observe($this->manager, EarlyWarningIndicator::sole(), ['observed_at' => '2026-10-01', 'value_text' => 'Belum jelas'])->assertSessionHasNoErrors();

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('relevance.observations', 0)->where('relevance.leading', null)->where('relevance.quadrants.0.supporting', 0));
    }

    /** Roadmap R2.3 (review trigger): an observation that crosses the threshold flags the framework and its scenarios for review. */
    public function test_a_threshold_crossing_flags_the_scenarios_for_review(): void
    {
        $framework = $this->readyFramework();
        $this->storeIndicator();
        $indicator = EarlyWarningIndicator::sole();

        $this->observe($this->manager, $indicator, ['observed_at' => '2026-09-30', 'value_numeric' => 4, 'direction' => 'rising'])->assertSessionHasNoErrors();
        $this->assertNull($framework->fresh()->stale_since);

        $this->observe($this->viewer, $indicator, ['observed_at' => '2026-10-31', 'value_numeric' => 11, 'direction' => 'rising', 'crossed_threshold' => true])->assertSessionHasNoErrors();

        $framework->refresh();
        $this->assertNotNull($framework->stale_since);
        $this->assertStringContainsString('xEV adoption growth', $framework->stale_reason);
        $this->assertStringContainsString('2026-10-31', $framework->stale_reason);
        $this->assertSame(4, $framework->quadrants()->whereNotNull('stale_since')->count());

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('project.stale.reasons', fn ($reasons) => collect($reasons)->contains(fn ($reason) => str_contains($reason, 'xEV adoption growth'))));

        $this->actingAs($this->manager)->post(route('scenario-planning.stale.acknowledge', $this->project))->assertSessionHasNoErrors();
        $this->assertNull($framework->fresh()->stale_since);
    }

    private function draftIndicator(string $name, array $directions = ['HH' => 'rising', 'HL' => 'falling', 'LH' => 'rising', 'LL' => 'falling'], array $overrides = []): array
    {
        return $overrides + [
            'name' => $name, 'description' => "{$name} per kuartal.", 'axis' => 'B',
            'metric' => 'Pangsa penjualan', 'unit' => '%', 'frequency' => 'quarterly', 'threshold_rule' => 'Di atas 10%',
            'directions' => $directions,
        ];
    }

    /** Roadmap R2.6 (indicators-v1): AI proposes indicators with a direction per quadrant; proposals wait for review. */
    public function test_ai_proposes_indicators_that_tell_the_scenarios_apart(): void
    {
        $this->readyFramework();
        $fake = $this->fakeLlm();
        $fake->respond(['indicators' => [$this->draftIndicator('Pangsa penjualan xEV')]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.suggest', $this->project))->assertSessionHasNoErrors();

        $this->assertSame('scenario_indicators', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('Technology Development', $fake->lastRequest()->user);
        $indicator = EarlyWarningIndicator::sole();
        $this->assertSame(['ai', 'proposed', 'quarterly', '%'], [$indicator->origin, $indicator->review_status, $indicator->frequency, $indicator->unit]);
        $this->assertSame('Technology Development', $indicator->uncertainty->name);
        $this->assertSame(4, $indicator->directions()->count());
        $this->assertSame('completed', AiGenerationRun::sole()->status);

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('indicators.0.origin', 'ai')->where('indicators.0.review_status', 'proposed')->where('indicatorStatus', 'completed'));

        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.accept', [$this->project, $indicator]))->assertSessionHasNoErrors();
        $this->assertSame('accepted', $indicator->fresh()->review_status);

        $fake->respond(['indicators' => [$this->draftIndicator('Jumlah SPKLU')]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.suggest', $this->project))->assertSessionHasNoErrors();
        $fake->respond(['indicators' => [$this->draftIndicator('Harga baterai per kWh')]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Pangsa penjualan xEV', 'Harga baterai per kWh'], EarlyWarningIndicator::pluck('name')->all());
    }

    public function test_ai_indicator_proposals_are_checked_and_follow_the_rules(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.suggest', $this->project))->assertSessionHasErrors('ai');
        $this->approveFramework()->assertSessionHasNoErrors();
        $this->actingAs($this->viewer)->post(route('scenario-planning.indicators.suggest', $this->project))->assertForbidden();

        $this->fakeLlm()->respond(['indicators' => [$this->draftIndicator('Sama di semua', ['HH' => 'rising', 'HL' => 'rising', 'LH' => 'rising', 'LL' => 'rising'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, EarlyWarningIndicator::count());
        $this->assertSame('rejected', AiGenerationRun::sole()->status);

        $this->fakeLlm()->respond(['indicators' => [$this->draftIndicator('Frekuensi aneh', overrides: ['frequency' => 'weekly'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, EarlyWarningIndicator::count());
    }

    /** Roadmap R2.6 (signal matching): AI proposes which new accepted signals evidence which indicator; proposals count only once confirmed. */
    public function test_ai_matches_new_signals_to_indicators_as_observations_awaiting_confirmation(): void
    {
        $this->readyFramework();
        $this->storeIndicator();
        $indicator = EarlyWarningIndicator::sole();
        $fresh = $this->signal('xEV sales up 40% in Q3');
        $cited = $this->signal('Charging network doubles');
        $this->signal('Unreviewed rumour', 'PENDING');
        $this->observe($this->manager, $indicator, ['observed_at' => '2026-09-30', 'value_text' => 'Sudah dicatat', 'signal' => $cited->uuid])->assertSessionHasNoErrors();
        $fake = $this->fakeLlm();
        $fake->respond(['matches' => [['indicator' => 'I1', 'signal' => 'S1', 'direction' => 'rising', 'note' => 'Penjualan xEV naik tajam.']]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.match', $this->project))->assertSessionHasNoErrors();

        $prompt = $fake->lastRequest()->user;
        $this->assertSame('indicator_signal_match', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('xEV sales up 40% in Q3', $prompt);
        $this->assertStringNotContainsString('Charging network doubles', $prompt);
        $this->assertStringNotContainsString('Unreviewed rumour', $prompt);
        $proposal = IndicatorObservation::where('origin', 'ai')->sole();
        $this->assertSame(['proposed', $fresh->id, 'rising'], [$proposal->review_status, $proposal->signal_id, $proposal->direction]);
        $this->assertSame('completed', AiGenerationRun::sole()->status);

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('relevance.observations', 0)->where('indicators.0.observations.0.review_status', 'proposed')->where('matchStatus', 'completed'));

        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.observations.confirm', [$this->project, $indicator, $proposal]))->assertSessionHasNoErrors();
        $this->assertSame(['accepted', $this->manager->id], [$proposal->fresh()->review_status, $proposal->fresh()->recorded_by]);
        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('relevance.observations', 1));
    }

    public function test_ai_signal_matching_is_checked_and_follows_the_rules(): void
    {
        $this->readyFramework();
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.match', $this->project))->assertSessionHasErrors('ai');
        $this->storeIndicator();
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.match', $this->project))->assertSessionHasErrors('ai');
        $this->signal('xEV sales up 40% in Q3');
        $this->actingAs($this->viewer)->post(route('scenario-planning.indicators.match', $this->project))->assertForbidden();

        $this->fakeLlm()->respond(['matches' => [['indicator' => 'I1', 'signal' => 'S7', 'direction' => 'rising', 'note' => 'x']]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.match', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, IndicatorObservation::count());
        $this->assertSame('rejected', AiGenerationRun::sole()->status);
    }
}
