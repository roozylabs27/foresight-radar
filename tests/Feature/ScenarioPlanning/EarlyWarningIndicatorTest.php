<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
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

    /** Deck slide 25: rising in FAVOUR (HH) and DICHOTOMY (HL), falling in STAGNATION (LL) and CHALLENGING (LH). */
    private function indicatorPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'xEV adoption growth',
            'description' => 'Pertumbuhan penjualan kendaraan listrik per tahun.',
            'uncertainty' => CriticalUncertainty::where('name', 'Technology Development')->value('uuid'),
            'metric' => 'Pangsa penjualan xEV', 'unit' => '%', 'baseline_value' => 3.5, 'baseline_date' => '2026-06-30',
            'frequency' => 'quarterly', 'source_type' => 'manual', 'threshold_rule' => 'Di atas 10% sebelum 2028',
            'directions' => [
                'HH' => ['direction' => 'rising'], 'HL' => ['direction' => 'rising'],
                'LL' => ['direction' => 'falling', 'note' => 'Lambat'], 'LH' => ['direction' => 'falling'],
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
        $this->assertEqualsCanonicalizing(['HH' => 'rising', 'HL' => 'rising', 'LL' => 'falling', 'LH' => 'falling'], $directions);
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
}
