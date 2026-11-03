<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R1.8: step 8 records strategic responses once per exercise, with
 * rationale, initiatives, priority, owner and links to step 7 items, and
 * rates their fit in each quadrant. Robustness is derived from the fit
 * (applies in 3+ quadrants = robust, 1 = scenario-specific, else
 * conditional). Approval is gate G7; any edit returns a response to proposed.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 8.
 */
class StrategicResponseTest extends TestCase
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

    private function responsePayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Secure USP for each xEV model',
            'description' => 'Diferensiasi produk di tiap segmen.',
            'rationale' => 'Persaingan merek baru menekan harga; USP menjaga margin.',
            'initiatives' => "Riset kebutuhan konsumen per segmen\nRoadmap fitur per model",
            'priority' => 1,
            'fit' => ['HH' => 'applies', 'HL' => 'not_applicable', 'LH' => 'partial', 'LL' => 'applies'],
        ];
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.responses.store', $this->project), $this->responsePayload($overrides));
    }

    private function action(StrategicResponse $response, string $name)
    {
        return $this->actingAs($this->manager)->post(route("scenario-planning.responses.{$name}", [$this->project, $response]));
    }

    public function test_responses_wait_for_an_approved_framework(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->store()->assertSessionHasErrors('responses');
        $this->assertSame(0, StrategicResponse::count());
    }

    public function test_the_deck_response_is_recorded_once_with_fit_per_quadrant_links_and_owner(): void
    {
        $this->readyFramework();
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.store', [$this->project, $this->quadrant('HH')]), ['type' => 'challenge', 'title' => 'Merek baru menekan harga'])->assertSessionHasNoErrors();
        $challenge = ScenarioImplication::sole();

        $this->store(['implications' => [$challenge->uuid], 'owner' => $this->manager->uuid, 'depends_on' => 'Harga baterai turun'])->assertSessionHasNoErrors();

        $response = StrategicResponse::sole();
        $this->assertSame(['proposed', 'human', 1, $this->manager->id], [$response->status, $response->origin, $response->priority, $response->owner_id]);
        $this->assertSame([$challenge->id], $response->implications()->pluck('scenario_implications.id')->all());
        $fit = $response->fits()->with('quadrant')->get()->mapWithKeys(fn ($row) => [$row->quadrant->position => $row->fit])->all();
        $this->assertEqualsCanonicalizing(['HH' => 'applies', 'HL' => 'not_applicable', 'LH' => 'partial', 'LL' => 'applies'], $fit);
        $this->assertSame('Harga baterai turun', $response->depends_on);
    }

    public function test_validation_rules(): void
    {
        $this->readyFramework();

        $this->store(['title' => str_repeat('a', 161)])->assertSessionHasErrors('title');
        $this->store(['fit' => ['HH' => 'maybe']])->assertSessionHasErrors('fit.HH');
        $this->store(['fit' => ['XX' => 'applies']])->assertSessionHasErrors('fit');
        $this->store(['priority' => 9])->assertSessionHasErrors('priority');
        $this->store(['implications' => [(string) Uuid::uuid4()]])->assertSessionHasErrors('implications');
        $this->assertSame(0, StrategicResponse::count());
    }

    public function test_approval_needs_a_rationale_and_fit_for_all_quadrants_and_edits_return_it_to_proposed(): void
    {
        $this->readyFramework();
        $this->store(['rationale' => null, 'fit' => ['HH' => 'applies']]);
        $response = StrategicResponse::sole();

        $this->action($response, 'approve')->assertSessionHasErrors(['rationale', 'fit']);

        $this->actingAs($this->manager)->put(route('scenario-planning.responses.update', [$this->project, $response]), $this->responsePayload())->assertSessionHasNoErrors();
        $this->action($response, 'approve')->assertSessionHasNoErrors();
        $response->refresh();
        $this->assertSame(['approved', $this->manager->id], [$response->status, $response->approved_by]);
        $this->assertNotNull($response->approved_at);

        $this->actingAs($this->manager)->put(route('scenario-planning.responses.update', [$this->project, $response]), $this->responsePayload(['initiatives' => 'Satu inisiatif saja']))->assertSessionHasNoErrors();
        $this->assertSame(['proposed', null], [$response->fresh()->status, $response->fresh()->approved_by]);

        $this->action($response, 'reject')->assertSessionHasNoErrors();
        $this->assertSame('rejected', $response->fresh()->status);
    }

    public function test_the_page_ranks_responses_by_robustness_and_separates_approved_ones(): void
    {
        $this->readyFramework();
        $this->store(['title' => 'Satu kuadran', 'fit' => ['HH' => 'applies', 'HL' => 'not_applicable', 'LH' => 'not_applicable', 'LL' => 'partial']]);
        $this->store(['title' => 'Tangguh', 'fit' => ['HH' => 'applies', 'HL' => 'applies', 'LH' => 'applies', 'LL' => 'partial']]);
        $this->store();
        $this->action(StrategicResponse::where('title', 'Tangguh')->sole(), 'approve')->assertSessionHasNoErrors();

        $this->actingAs($this->viewer)->get(route('scenario-planning.responses.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Responses')
                ->where('responses.0.title', 'Tangguh')
                ->where('responses.0.robustness', 'robust')
                ->where('responses.0.applies', 3)
                ->where('responses.0.status', 'approved')
                ->where('responses.1.title', 'Secure USP for each xEV model')
                ->where('responses.1.robustness', 'conditional')
                ->where('responses.2.robustness', 'scenario_specific')
                ->where('responses.2.status', 'proposed')
                ->has('quadrants', 4)
                ->where('canManage', false));
    }

    public function test_step_eight_is_done_with_an_approved_response(): void
    {
        $this->readyFramework();
        $step = fn () => $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project));

        $step()->assertInertia(fn ($page) => $page->where('steps.7.status', 'available'));
        $this->store();
        $step()->assertInertia(fn ($page) => $page->where('steps.7.status', 'in_progress'));
        $this->action(StrategicResponse::sole(), 'approve');
        $step()->assertInertia(fn ($page) => $page->where('steps.7.status', 'done'));
    }

    public function test_viewers_and_other_exercises_cannot_change_responses(): void
    {
        $this->readyFramework();
        $this->store();
        $response = StrategicResponse::sole();

        $this->actingAs($this->viewer)->post(route('scenario-planning.responses.approve', [$this->project, $response]))->assertForbidden();
        $other = $this->exercise();
        $this->actingAs($this->manager)->delete(route('scenario-planning.responses.destroy', [$other, $response]))->assertNotFound();

        $this->actingAs($this->manager)->delete(route('scenario-planning.responses.destroy', [$this->project, $response]))->assertSessionHasNoErrors();
        $this->assertSame(0, StrategicResponse::count());
    }

    private function condition(string $position, string $text): ScenarioCondition
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant($position)]), ['kind' => 'assumption', 'text' => $text])->assertSessionHasNoErrors();

        return ScenarioCondition::latest('id')->firstOrFail();
    }

    /** Roadmap R2.9: a response names the step 5 conditions it relies on. */
    public function test_a_response_lists_the_conditions_it_relies_on(): void
    {
        $this->readyFramework();
        $battery = $this->condition('HH', 'Harga baterai turun 40%');
        $this->condition('LL', 'Subsidi EV dihentikan');

        $this->store(['conditions' => [$battery->uuid]])->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();
        $this->assertSame([$battery->id], $response->conditions()->pluck('scenario_conditions.id')->all());

        $this->store(['title' => 'Lain', 'conditions' => [(string) Uuid::uuid4()]])->assertSessionHasErrors('conditions');
        $this->assertSame(1, StrategicResponse::count());

        $this->actingAs($this->manager)->get(route('scenario-planning.responses.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->has('conditions', 2)
                ->where('responses.0.conditions.0.id', $battery->uuid)
                ->where('responses.0.conditions.0.position', 'HH')
                ->where('responses.0.conditions.0.text', 'Harga baterai turun 40%')
                ->where('responses.0.stale', null));
    }

    /** Changing or removing a relied-on condition flags the response, keeps its status, until kept or edited. */
    public function test_changing_a_relied_on_condition_flags_the_response(): void
    {
        $this->readyFramework();
        $battery = $this->condition('HH', 'Harga baterai turun 40%');
        $subsidy = $this->condition('LL', 'Subsidi EV dihentikan');
        $this->store(['conditions' => [$battery->uuid]]);
        $this->store(['title' => 'Respons lain', 'conditions' => [$subsidy->uuid]]);
        [$relying, $other] = [StrategicResponse::where('title', 'Secure USP for each xEV model')->sole(), StrategicResponse::where('title', 'Respons lain')->sole()];
        $this->action($relying, 'approve')->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->put(route('scenario-planning.conditions.update', [$this->project, $battery]), ['kind' => 'assumption', 'text' => 'Harga baterai stagnan'])->assertSessionHasNoErrors();

        $relying->refresh();
        $this->assertNotNull($relying->stale_since);
        $this->assertStringContainsString('Harga baterai stagnan', $relying->stale_reason);
        $this->assertSame('approved', $relying->status);
        $this->assertNull($other->fresh()->stale_since);
        $this->actingAs($this->manager)->get(route('scenario-planning.responses.index', $this->project))
            ->assertInertia(fn ($page) => $page // equal fit and priority: sorted by title
                ->where('responses.0.title', 'Respons lain')->where('responses.0.stale', null)
                ->where('responses.1.stale.reasons.0', $relying->stale_reason));

        $this->action($relying, 'keep')->assertSessionHasNoErrors();
        $this->assertNull($relying->fresh()->stale_since);
        $this->assertSame('approved', $relying->fresh()->status);

        $this->actingAs($this->manager)->delete(route('scenario-planning.conditions.destroy', [$this->project, $subsidy]))->assertSessionHasNoErrors();
        $other->refresh();
        $this->assertNotNull($other->stale_since);
        $this->assertStringContainsString('Subsidi EV dihentikan', $other->stale_reason);
        $this->assertSame(0, $other->conditions()->count());

        $this->actingAs($this->manager)->put(route('scenario-planning.responses.update', [$this->project, $other]), $this->responsePayload(['title' => 'Respons lain']))->assertSessionHasNoErrors();
        $this->assertNull($other->fresh()->stale_since);

        $this->actingAs($this->viewer)->post(route('scenario-planning.responses.keep', [$this->project, $relying]))->assertForbidden();
    }
}
