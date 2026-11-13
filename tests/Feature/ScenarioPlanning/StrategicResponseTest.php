<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\AiGenerationRun;
use App\Models\Comment;
use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\ProjectDecision;
use App\Models\ProjectDrivingForce;
use App\Models\ProjectParticipant;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\ProjectAssessmentService;
use App\Services\ProjectDrivingForceService;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioPlanningProjectService;
use App\Services\StrategicResponseService;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\KeepsLibraryScores;
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
    use KeepsLibraryScores, RefreshDatabase;

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
            $this->keepLibraryScores($this->manager, $project);
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
        $this->approveNarratives();
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
        $this->approveNarratives();
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
        $this->approveNarratives();
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
        $this->approveNarratives();
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

    /** @return array<int, string> "G1 approved" … in the order they were logged */
    private function decisions(): array
    {
        return ProjectDecision::where('project_id', $this->project->id)->orderBy('id')->get()->map(fn ($row) => "{$row->gate} {$row->action}")->all();
    }

    /** Roadmap R2.2 (decision log): every gate decision is recorded with who made it, newest first on the overview. */
    public function test_every_gate_decision_is_logged_with_its_actor(): void
    {
        $this->readyFramework();
        $this->approveNarratives();
        $this->store()->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();
        $this->action($response, 'approve')->assertSessionHasNoErrors();
        $this->action($response, 'reject')->assertSessionHasNoErrors();

        $this->assertSame([
            'G1 approved', 'ST active', 'G2 approved', 'G3 approved', 'G4 approved', 'G4 approved', 'G5 approved', 'G7 approved', 'G7 rejected',
        ], $this->decisions());
        $this->assertSame([$this->manager->id], ProjectDecision::distinct()->pluck('actor_id')->all());

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('decisions.0.gate', 'G7')
                ->where('decisions.0.action', 'rejected')
                ->where('decisions.0.subject', 'Secure USP for each xEV model')
                ->where('decisions.0.actor', $this->manager->name)
                ->where('decisions.2.gate', 'G5')
                ->has('decisions', 9));
    }

    public function test_withdrawn_gates_are_logged_once_each(): void
    {
        $this->readyFramework();

        $this->actingAs($this->manager)->put(route('scenario-planning.assessment.update', [$this->project, $this->link($this->project, 'Politic')]), ['impact' => 3, 'uncertainty' => 8])->assertSessionHasNoErrors();

        $withdrawn = array_values(array_filter($this->decisions(), fn ($row) => str_ends_with($row, 'withdrawn')));
        $this->assertSame(['G3 withdrawn', 'G5 withdrawn', 'G4 withdrawn', 'G4 withdrawn'], $withdrawn);
        $this->assertSame('Government Regulation', ProjectDecision::where('gate', 'G4')->where('action', 'withdrawn')->orderBy('id')->first()->subject);
    }

    private function comment(User $user, string $type, string $id, string $body = 'Perlu data pasar terbaru.')
    {
        return $this->actingAs($user)->post(route('scenario-planning.comments.store', $this->project), ['type' => $type, 'id' => $id, 'body' => $body]);
    }

    /** Roadmap R2.2 (comments): threads on forces, uncertainties, quadrants and responses; open counts on their pages. */
    public function test_comments_on_each_artefact_of_the_exercise(): void
    {
        [$regulation] = $this->approveAxes();
        $this->createFramework();
        $this->approveFramework();
        $this->store();
        $response = StrategicResponse::sole();
        $force = $this->link($this->project, 'Politic');

        $this->comment($this->viewer, 'force', $force->uuid)->assertSessionHasNoErrors();
        $this->comment($this->manager, 'uncertainty', $regulation->uuid)->assertSessionHasNoErrors();
        $this->comment($this->manager, 'quadrant', $this->quadrant('HH')->uuid)->assertSessionHasNoErrors();
        $this->comment($this->viewer, 'response', $response->uuid, 'Siapa pemilik inisiatif ini?')->assertSessionHasNoErrors();

        $this->assertSame(4, Comment::where('project_id', $this->project->id)->count());
        $this->assertTrue(Comment::where('body', 'Siapa pemilik inisiatif ini?')->sole()->commentable->is($response));

        $this->actingAs($this->viewer)->getJson(route('scenario-planning.comments.index', [$this->project, 'type' => 'response', 'id' => $response->uuid]))
            ->assertOk()
            ->assertJsonPath('comments.0.body', 'Siapa pemilik inisiatif ini?')
            ->assertJsonPath('comments.0.author', $this->viewer->name)
            ->assertJsonPath('comments.0.can_delete', true)
            ->assertJsonPath('can_comment', true);

        $this->actingAs($this->viewer)->get(route('scenario-planning.responses.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('responses.0.open_comments', 1));
        $this->actingAs($this->viewer)->get(route('scenario-planning.forces.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('forces.0.open_comments', 1));
        $this->actingAs($this->viewer)->get(route('scenario-planning.uncertainties.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('uncertainties.0.open_comments', 1));
        $this->actingAs($this->viewer)->get(route('scenario-planning.conditions.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('quadrants.0.position', 'HH')->where('quadrants.0.open_comments', 1));
    }

    public function test_comment_rules(): void
    {
        $this->readyFramework();
        $force = $this->link($this->project, 'Politic');

        $this->comment($this->viewer, 'signal', $force->uuid)->assertSessionHasErrors('type');
        $this->comment($this->viewer, 'force', $force->uuid, '')->assertSessionHasErrors('body');
        $other = $this->exercise();
        $this->comment($this->viewer, 'force', $this->link($other, 'Politic')->uuid)->assertSessionHasErrors('id');

        $this->comment($this->viewer, 'force', $force->uuid)->assertSessionHasNoErrors();
        $comment = Comment::sole();
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);

        $this->actingAs($colleague)->post(route('scenario-planning.comments.resolve', [$this->project, $comment]))->assertForbidden();
        $this->actingAs($colleague)->delete(route('scenario-planning.comments.destroy', [$this->project, $comment]))->assertForbidden();
        $this->actingAs($this->viewer)->post(route('scenario-planning.comments.resolve', [$this->project, $comment]))->assertSessionHasNoErrors();
        $this->assertNotNull($comment->fresh()->resolved_at);
        $this->actingAs($this->manager)->post(route('scenario-planning.comments.resolve', [$this->project, $comment]))->assertSessionHasNoErrors();
        $this->assertNull($comment->fresh()->resolved_at);

        // Once participants are listed, a viewer-role participant reads but does not comment.
        ProjectParticipant::create(['project_id' => $this->project->id, 'tenant_id' => $this->project->tenant_id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
        $this->comment($this->viewer, 'force', $force->uuid)->assertForbidden();
        $this->actingAs($this->viewer)->getJson(route('scenario-planning.comments.index', [$this->project, 'type' => 'force', 'id' => $force->uuid]))
            ->assertOk()->assertJsonPath('can_comment', false)->assertJsonCount(1, 'comments');

        $this->actingAs($this->manager)->delete(route('scenario-planning.comments.destroy', [$this->project, $comment]))->assertSessionHasNoErrors();
        $this->assertSame(0, Comment::count());
    }

    private function implication(string $position, string $type, string $title): ScenarioImplication
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.store', [$this->project, $this->quadrant($position)]), ['type' => $type, 'title' => $title])->assertSessionHasNoErrors();

        return ScenarioImplication::where('title', $title)->sole();
    }

    private function draftResponse(string $title, array $refs, array $fit = ['HH' => 'applies', 'HL' => 'applies', 'LH' => 'partial', 'LL' => 'applies']): array
    {
        return ['title' => $title, 'description' => "{$title}.", 'rationale' => 'Menjawab tantangan di beberapa skenario.', 'initiatives' => "Inisiatif satu\nInisiatif dua", 'priority' => 2, 'fit' => $fit, 'implication_refs' => $refs];
    }

    /** Roadmap R2.6 (response-v1): AI proposes responses citing implications, with fit per quadrant; they stay proposed until G7. */
    public function test_ai_proposes_responses_citing_implications_that_wait_for_approval(): void
    {
        $this->readyFramework();
        $price = $this->implication('HH', 'challenge', 'Perang harga produsen baru');
        $this->implication('LL', 'opportunity', 'Pasar mobil bekas tumbuh');
        $fake = $this->fakeLlm();
        $fake->respond(['responses' => [$this->draftResponse('Diversifikasi rantai pasok baterai', ['I1'])]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.responses.suggest', $this->project))->assertSessionHasNoErrors();

        $this->assertSame('strategic_responses', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('Perang harga produsen baru', $fake->lastRequest()->user);
        $response = StrategicResponse::sole();
        $this->assertSame(['ai', 'proposed', 2], [$response->origin, $response->status, $response->priority]);
        $this->assertSame([$price->id], $response->implications()->pluck('scenario_implications.id')->all());
        $this->assertSame(4, $response->fits()->count());
        $this->assertSame('completed', AiGenerationRun::sole()->status);

        $this->actingAs($this->viewer)->get(route('scenario-planning.responses.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('responses.0.origin', 'ai')->where('responses.0.status', 'proposed')->where('responseStatus', 'completed'));

        $this->approveNarratives();
        $this->action($response, 'approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $response->fresh()->status);

        // A new run replaces only AI responses still proposed; the approved one stays.
        $fake->respond(['responses' => [$this->draftResponse('Kemitraan daur ulang baterai', ['I2'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.responses.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Diversifikasi rantai pasok baterai', 'Kemitraan daur ulang baterai'], StrategicResponse::pluck('title')->all());
        $fake->respond(['responses' => [$this->draftResponse('Satu lagi', ['I2'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.responses.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Diversifikasi rantai pasok baterai', 'Satu lagi'], StrategicResponse::pluck('title')->all());
    }

    public function test_ai_response_proposals_are_checked_and_follow_the_rules(): void
    {
        $this->readyFramework();
        $this->actingAs($this->manager)->post(route('scenario-planning.responses.suggest', $this->project))->assertSessionHasErrors('ai');
        $this->implication('HH', 'challenge', 'Perang harga produsen baru');
        $this->actingAs($this->viewer)->post(route('scenario-planning.responses.suggest', $this->project))->assertForbidden();

        $this->fakeLlm()->respond(['responses' => [$this->draftResponse('Rujukan salah', ['I9'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.responses.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, StrategicResponse::count());
        $this->assertSame('rejected', AiGenerationRun::sole()->status);

        $this->fakeLlm()->respond(['responses' => [$this->draftResponse('Kecocokan kurang', ['I1'], ['HH' => 'applies'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.responses.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, StrategicResponse::count());
    }

    /**
     * Production readiness PRD-11: two simultaneous requests for the same gate each loaded the record
     * before the other wrote it. Simulated with two copies loaded up front: the gate is passed once
     * and logged once.
     */
    public function test_a_gate_passed_twice_from_stale_copies_is_logged_once(): void
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Paralel', 'focal_question' => 'Bagaimana pasar baterai pada 2030?', 'purpose' => 'Uji paralel.', 'target_year' => now()->year + 4,
        ])->assertSessionHasNoErrors();
        $fresh = ScenarioPlanningProject::where('name', 'Paralel')->sole();
        $twice = function (callable $pass, callable $load) {
            [$first, $second] = [$load(), $load()];
            $pass($first);
            $pass($second);
        };

        $twice(fn ($p) => app(ScenarioPlanningProjectService::class)->confirm($p, $this->manager), fn () => $fresh->fresh());
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $fresh), ['forces' => [$this->forces['Politic']->uuid, $this->forces['Technology']->uuid]])->assertSessionHasNoErrors();
        $twice(fn ($p) => app(ProjectDrivingForceService::class)->confirm($p, $this->manager), fn () => $fresh->fresh());
        $this->keepLibraryScores($this->manager, $fresh);
        $twice(fn ($p) => app(ProjectAssessmentService::class)->confirm($p, $this->manager), fn () => $fresh->fresh());

        $this->readyFramework();
        DB::table('scenario_quadrants')->where('scenario_id', $this->framework()->id)->update(['narrative' => 'Narasi tim.', 'edited_at' => now()]);
        foreach (Scenario::POSITIONS as $position) {
            $twice(fn ($q) => app(ScenarioNarrativeService::class)->reviewQuadrant($q, 'approved', $this->manager->id), fn () => $this->quadrant($position));
        }
        $this->store()->assertSessionHasNoErrors();
        $twice(fn ($r) => app(StrategicResponseService::class)->approve($r, $this->manager), fn () => StrategicResponse::sole());

        $count = fn (ScenarioPlanningProject $project, string $gate) => ProjectDecision::where('project_id', $project->id)->where('gate', $gate)->where('action', 'approved')->count();
        $this->assertSame([1, 1, 1], [$count($fresh, 'G1'), $count($fresh, 'G2'), $count($fresh, 'G3')]);
        $this->assertSame([4, 1], [$count($this->project, 'G6'), $count($this->project, 'G7')]);
    }

    /** Gate rationale (#13): an optional reason and the artefact are kept with each decision. */
    public function test_a_gate_decision_keeps_its_reason_and_artefact(): void
    {
        $this->readyFramework();
        $this->approveNarratives();
        $this->store()->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();

        $this->actingAs($this->manager)->post(route('scenario-planning.responses.approve', [$this->project, $response]), ['rationale' => 'Disepakati di lokakarya kedua.'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('scenario-planning.framework.reopen', $this->project), ['rationale' => str_repeat('x', 1001)])->assertSessionHasErrors('rationale');

        $g7 = ProjectDecision::where('gate', 'G7')->sole();
        $this->assertSame(['Disepakati di lokakarya kedua.', 'strategic_response', $response->id], [$g7->rationale, $g7->artefact_type, $g7->artefact_id]);
        $g5 = ProjectDecision::where('gate', 'G5')->where('action', 'approved')->sole();
        $this->assertSame(['scenario', $this->framework()->id, null], [$g5->artefact_type, $g5->artefact_id, $g5->rationale]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('decisions.0.gate', 'G7')->where('decisions.0.rationale', 'Disepakati di lokakarya kedua.'));
    }

    /** Gate rationale (#13): exercise status changes are logged too. */
    public function test_exercise_status_changes_are_logged(): void
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.status', $this->project), ['status' => 'archived', 'rationale' => 'Latihan ditunda.'])->assertSessionHasNoErrors();

        $row = ProjectDecision::where('gate', 'ST')->where('action', 'archived')->sole();
        $this->assertSame(['archived', 'Latihan ditunda.', 'scenario_planning_project', $this->project->id], [$row->action, $row->rationale, $row->artefact_type, $row->artefact_id]);
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('decisions.0.label', 'Status latihan'));
    }

    /* Audit 2026-11-11 U6 (AUD-07, AUD-08): upstream changes reach Steps 7–9; G7 needs G6. */

    /** All four narratives approved (G6), written directly so the decision log stays as the test expects. */
    private function approveNarratives(): void
    {
        DB::table('scenario_quadrants')->where('scenario_id', $this->framework()->id)
            ->update(['narrative' => 'Narasi disepakati tim.', 'review_status' => 'approved', 'reviewed_by' => $this->manager->id, 'reviewed_at' => now()]);
    }

    private function indicator(string $name = 'xEV adoption growth'): EarlyWarningIndicator
    {
        return EarlyWarningIndicator::create([
            'project_id' => $this->project->id, 'name' => $name, 'frequency' => 'quarterly', 'source_type' => 'manual',
            'status' => 'active', 'origin' => 'human', 'review_status' => 'accepted',
        ]);
    }

    public function test_a_response_is_approved_only_after_all_four_narratives(): void
    {
        $this->readyFramework();
        $this->store()->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();

        $this->action($response, 'approve')->assertSessionHasErrors('narratives');
        $this->assertSame('proposed', $response->fresh()->status);

        $this->approveNarratives();
        $this->action($response, 'approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $response->fresh()->status);
    }

    public function test_reopening_the_framework_flags_implications_responses_and_indicators(): void
    {
        $this->readyFramework();
        $implication = $this->implication('HH', 'opportunity', 'Pasar baterai tumbuh');
        $this->store()->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();
        $indicator = $this->indicator();

        $this->actingAs($this->manager)->post(route('scenario-planning.framework.reopen', $this->project))->assertSessionHasNoErrors();

        foreach ([$implication, $response, $indicator] as $row) {
            $this->assertNotNull($row->fresh()->stale_since, $row::class);
            $this->assertStringContainsString('Kerangka skenario dibuka kembali', (string) $row->fresh()->stale_reason, $row::class);
        }
        // Nothing is deleted.
        $this->assertSame([1, 1, 1], [ScenarioImplication::count(), StrategicResponse::count(), EarlyWarningIndicator::count()]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('project.stale.downstream.implications', 1)
                ->where('project.stale.downstream.responses', 1)
                ->where('project.stale.downstream.indicators', 1));
    }

    /** PI-03: reopening G5 withdraws G6 and G7 (logged); stale work cannot be approved until reviewed. */
    public function test_reopening_the_framework_withdraws_narrative_and_response_approvals(): void
    {
        $this->readyFramework();
        $this->store()->assertSessionHasNoErrors();
        $response = StrategicResponse::sole();
        foreach (Scenario::POSITIONS as $position) {
            $this->editNarrative($this->quadrant($position), "Narasi {$position} disepakati tim.")->assertOk();
            $this->reviewNarrative($this->quadrant($position))->assertOk();
        }
        $this->action($response, 'approve')->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->post(route('scenario-planning.framework.reopen', $this->project))->assertSessionHasNoErrors();

        $this->assertSame(0, ScenarioQuadrant::where('review_status', 'approved')->count());
        $this->assertSame('proposed', $response->fresh()->status);
        $this->assertNull($response->fresh()->approved_at);
        $decisions = $this->decisions();
        $this->assertSame(4, count(array_keys($decisions, 'G6 withdrawn')));
        $this->assertContains('G7 withdrawn', $decisions);

        // Re-approve G5: the flagged narrative and response wait for a review.
        $this->approveFramework()->assertSessionHasNoErrors();
        $quadrant = $this->quadrant('HH');
        $this->assertNotNull($quadrant->stale_since);
        $this->reviewNarrative($quadrant)->assertJsonValidationErrors('status');
        $this->editNarrative($quadrant, 'Narasi HH ditinjau ulang.')->assertOk();
        $this->reviewNarrative($quadrant->fresh())->assertOk();

        $this->approveNarratives();
        $this->action($response, 'approve')->assertSessionHasErrors('stale');
        $this->action($response, 'keep')->assertSessionHasNoErrors();
        $this->action($response, 'approve')->assertSessionHasNoErrors();
    }

    private function editNarrative(ScenarioQuadrant $quadrant, string $narrative)
    {
        return $this->actingAs($this->manager)->putJson(route('scenario-planning.narratives.quadrant', [$this->project, $quadrant]), ['title' => $quadrant->title, 'narrative' => $narrative]);
    }

    private function reviewNarrative(ScenarioQuadrant $quadrant)
    {
        return $this->actingAs($this->manager)->postJson(route('scenario-planning.narratives.review', [$this->project, $quadrant]), ['status' => 'approved']);
    }

    public function test_a_new_axis_flags_steps_seven_to_nine(): void
    {
        $this->readyFramework();
        $implication = $this->implication('LL', 'challenge', 'Permintaan turun');
        $indicator = $this->indicator();

        // Withdrawing an approved uncertainty withdraws G5: downstream work is flagged.
        $regulation = CriticalUncertainty::where('name', 'Government Regulation')->sole();
        $this->actingAs($this->manager)->post(route('scenario-planning.uncertainties.withdraw', [$this->project, $regulation]))->assertSessionHasNoErrors();

        $this->assertNotNull($implication->fresh()->stale_since);
        $this->assertNotNull($indicator->fresh()->stale_since);
    }

    public function test_changing_an_implication_flags_the_responses_that_rely_on_it(): void
    {
        $this->readyFramework();
        $relied = $this->implication('HH', 'opportunity', 'Pasar baterai tumbuh');
        $this->implication('HL', 'challenge', 'Biaya impor naik');
        $this->store(['title' => 'Bergantung', 'implications' => [$relied->uuid]])->assertSessionHasNoErrors();
        $this->store(['title' => 'Mandiri'])->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->put(route('scenario-planning.implications.update', [$this->project, $relied]), ['type' => 'opportunity', 'title' => 'Pasar baterai tumbuh pesat'])->assertSessionHasNoErrors();

        $flagged = StrategicResponse::where('title', 'Bergantung')->sole();
        $this->assertStringContainsString('Pasar baterai tumbuh pesat', (string) $flagged->stale_reason);
        $this->assertNull(StrategicResponse::where('title', 'Mandiri')->sole()->stale_since);

        // Removing it flags them too; the response keeps its other content.
        $this->actingAs($this->manager)->post(route('scenario-planning.responses.keep', [$this->project, $flagged]))->assertSessionHasNoErrors();
        $this->assertNull($flagged->fresh()->stale_since);
        $this->actingAs($this->manager)->delete(route('scenario-planning.implications.destroy', [$this->project, $relied->fresh()]))->assertSessionHasNoErrors();
        $this->assertStringContainsString('dihapus', (string) $flagged->fresh()->stale_reason);
    }

    public function test_regenerating_a_narrative_flags_the_implications_of_that_quadrant(): void
    {
        $this->readyFramework();
        // Without an AI provider the generation writes the template skeleton.
        $this->actingAs($this->manager)->postJson(route('scenario-planning.narratives.generate', $this->project))->assertStatus(202);
        $hh = $this->implication('HH', 'opportunity', 'Pasar baterai tumbuh');
        $this->assertNull($hh->fresh()->stale_since);

        $this->actingAs($this->manager)->postJson(route('scenario-planning.narratives.generate', $this->project), ['overwrite_edited' => true])->assertStatus(202);

        $this->assertStringContainsString('Narasi skenario dibuat ulang', (string) $hh->fresh()->stale_reason);
    }

    public function test_editing_clears_an_item_flag_and_keeping_the_framework_clears_the_rest(): void
    {
        $this->readyFramework();
        $edited = $this->implication('HH', 'opportunity', 'Pasar baterai tumbuh');
        $kept = $this->implication('HL', 'challenge', 'Biaya impor naik');
        $indicator = $this->indicator();
        $this->actingAs($this->manager)->post(route('scenario-planning.framework.reopen', $this->project))->assertSessionHasNoErrors();
        $this->approveFramework()->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->put(route('scenario-planning.implications.update', [$this->project, $edited]), ['type' => 'opportunity', 'title' => 'Pasar baterai tumbuh'])->assertSessionHasNoErrors();
        $this->assertNull($edited->fresh()->stale_since);
        $this->assertNotNull($kept->fresh()->stale_since);

        $this->actingAs($this->manager)->post(route('scenario-planning.stale.acknowledge', $this->project))->assertSessionHasNoErrors();
        $this->assertNull($kept->fresh()->stale_since);
        $this->assertNull($indicator->fresh()->stale_since);
    }
}
