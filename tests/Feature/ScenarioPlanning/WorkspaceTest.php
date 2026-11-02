<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R1.11: the exercise workspace. Every step page carries the nine
 * steps for the stepper; the overview names the next action in plain words
 * and the last gate passed; the sidebar groups items (Scenario Planning,
 * Library, Analysis, Governance) without removing any route.
 * See docs/product/SCENARIO_PLANNING_TARGET_WORKFLOW.md §7.
 */
class WorkspaceTest extends TestCase
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

    public function test_every_step_page_carries_the_nine_steps_for_the_stepper(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->approveFramework()->assertSessionHasNoErrors();

        foreach (['forces', 'assessment', 'uncertainties', 'framework', 'conditions', 'implications', 'responses', 'indicators'] as $page) {
            $this->actingAs($this->viewer)->get(route("scenario-planning.{$page}.index", $this->project))
                ->assertOk()
                ->assertInertia(fn ($inertia) => $inertia->has('steps', 9)->where('steps.3.status', 'done')->where('steps.3.href', route('scenario-planning.framework.index', $this->project)));
        }
    }

    public function test_the_overview_names_the_next_action_and_the_last_gate(): void
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Baru', 'focal_question' => 'Bagaimana pasar baterai pada 2030?', 'purpose' => 'Uji.', 'target_year' => now()->year + 4,
        ]);
        $fresh = ScenarioPlanningProject::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->get(route('scenario-planning.show', $fresh))
            ->assertInertia(fn ($page) => $page->where('nextAction.number', 1)->where('lastGate', null));

        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $fresh));
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $fresh))
            ->assertInertia(fn ($page) => $page->where('nextAction.number', 2)
                ->where('nextAction.href', route('scenario-planning.forces.index', $fresh))
                ->where('lastGate.gate', 'G1')
                ->where('lastGate.by', $this->manager->name));

        // The sample exercise has agreed forces and scores: its last gate is G3 and step 3 is next.
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('lastGate.gate', 'G3')->where('nextAction.number', 3));

        $this->approveAxes();
        $this->createFramework();
        $this->approveFramework();
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('lastGate.gate', 'G5')->where('nextAction.number', 5));
    }

    public function test_the_sidebar_groups_items_without_removing_any_route(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.jsx'));

        foreach (['dashboard.', 'scenario-planning.index', 'signals.index', 'driving-force.', 'time-horizon.', 'rating-urgency.', 'status-action.', 'approval-items.', 'closed-items.',
            'visualization.prioritizing.', 'visualization.registered-list.', 'visualization.foresight-radar.', 'visualization.cross-impact.', 'visualization.knowledge-graph.', 'scenarios.index', 'settings.index'] as $route) {
            $this->assertStringContainsString("route: \"{$route}\"", $layout, "Sidebar lost {$route}");
        }
        foreach (['scenario_planning', 'library', 'analysis', 'governance'] as $group) {
            $this->assertStringContainsString("group: \"{$group}\"", $layout);
        }
    }
}
