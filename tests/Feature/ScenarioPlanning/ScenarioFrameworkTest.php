<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioQuadrant;
use App\Models\ScenarioPlanningProject;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R0.5: step 4 turns the two approved critical uncertainties into a
 * 2x2 framework. Saving it creates the four quadrants (no narrative yet), the
 * team names and summarises them, and approves the framework (gate G5), which
 * locks poles and names and opens steps 5-9. Narrative generation fills the
 * existing quadrant rows instead of recreating them.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 4.
 */
class ScenarioFrameworkTest extends TestCase
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

    public function test_a_framework_needs_two_approved_uncertainties(): void
    {
        $this->twoProposals();

        $this->createFramework()->assertSessionHasErrors('framework');
        $this->assertSame(0, Scenario::count());
    }

    public function test_saving_creates_four_quadrants_named_after_the_poles_without_narrative(): void
    {
        [$regulation, $technology] = $this->approveAxes();

        $this->createFramework()->assertSessionHasNoErrors();

        $framework = $this->framework();
        $this->assertSame([$regulation->id, $technology->id], [$framework->axis_a_uncertainty_id, $framework->axis_b_uncertainty_id]);
        $this->assertSame(['Unfavorable', 'Favorable', 'Slow', 'Fast'], [$framework->axis_a_low_label, $framework->axis_a_high_label, $framework->axis_b_low_label, $framework->axis_b_high_label]);
        $this->assertSame('draft', $framework->framework_status);
        $this->assertSame($this->project->focal_question, $framework->focal_question);
        $this->assertNull($framework->axis_a_force_id);

        $quadrants = $framework->quadrants()->get()->keyBy('position');
        $this->assertSame(Scenario::POSITIONS, $quadrants->keys()->all());
        $this->assertSame('Favorable · Slow', $quadrants['HL']->title);
        $this->assertNull($quadrants['HL']->narrative);
        $this->assertNull($quadrants['HL']->edited_at);

        $this->createFramework()->assertSessionHasErrors('framework');
        $this->assertSame(1, Scenario::count());
    }

    public function test_the_page_shows_axis_names_from_the_uncertainties(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->actingAs($this->viewer)->get(route('scenario-planning.framework.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Framework')
                ->where('framework.axes.0.name', 'Government Regulation')
                ->where('framework.axes.1.name', 'Technology Development')
                ->where('framework.axes.0.forces', ['Politic', 'Legislative'])
                ->has('framework.quadrants', 4)
                ->where('canManage', false));
    }

    public function test_viewers_cannot_change_the_framework(): void
    {
        $this->approveAxes();

        $this->actingAs($this->viewer)->post(route('scenario-planning.framework.store', $this->project))->assertForbidden();
        $this->assertSame(0, Scenario::count());
    }

    public function test_changing_poles_renames_quadrants_that_still_carry_the_default_name(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $custom = $this->quadrant('LL');
        $this->actingAs($this->manager)->put(route('scenario-planning.framework.quadrant', [$this->project, $custom]), ['title' => 'Jalan di tempat', 'summary' => 'Regulasi lemah dan teknologi lambat.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->put(route('scenario-planning.framework.update', $this->project), $this->poles(['axis_b_high_label' => 'Breakthrough']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Breakthrough', $this->framework()->axis_b_high_label);
        $this->assertSame('Favorable · Breakthrough', $this->quadrant('HH')->title);
        $this->assertSame('Jalan di tempat', $this->quadrant('LL')->title);
        $this->assertSame('Regulasi lemah dan teknologi lambat.', $this->quadrant('LL')->summary);
        $this->assertNull($this->quadrant('LL')->edited_at);

        $this->actingAs($this->manager)->put(route('scenario-planning.framework.update', $this->project), $this->poles(['axis_a_high_label' => 'Unfavorable']))
            ->assertSessionHasErrors('axis_a_high_label');
    }

    public function test_quadrant_names_must_be_unique(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->actingAs($this->manager)->put(route('scenario-planning.framework.quadrant', [$this->project, $this->quadrant('LL')]), ['title' => 'Favorable · Fast'])
            ->assertSessionHasErrors('title');
    }

    public function test_approval_locks_poles_and_names_and_opens_step_five_until_reopened(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('steps.3.status', 'in_progress')->where('steps.4.status', 'locked'));

        $this->approveFramework()->assertSessionHasNoErrors();

        $framework = $this->framework();
        $this->assertSame('approved', $framework->framework_status);
        $this->assertSame($this->manager->id, $framework->approved_by);
        $this->assertNotNull($framework->approved_at);
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('steps.3.status', 'done')->where('steps.4.status', 'available'));

        $this->actingAs($this->manager)->put(route('scenario-planning.framework.update', $this->project), $this->poles(['axis_b_high_label' => 'Breakthrough']))
            ->assertSessionHasErrors('framework');
        $this->actingAs($this->manager)->put(route('scenario-planning.framework.quadrant', [$this->project, $this->quadrant('LL')]), ['title' => 'Baru'])
            ->assertSessionHasErrors('framework');

        $this->actingAs($this->manager)->post(route('scenario-planning.framework.reopen', $this->project))->assertSessionHasNoErrors();
        $this->assertSame('draft', $this->framework()->framework_status);
        $this->assertNull($this->framework()->approved_by);
    }

    public function test_withdrawing_an_axis_withdraws_the_framework_and_a_new_axis_replaces_it(): void
    {
        [$regulation] = $this->approveAxes();
        $this->createFramework();
        $this->approveFramework();

        $this->actingAs($this->manager)->post(route('scenario-planning.uncertainties.withdraw', [$this->project, $regulation]))->assertSessionHasNoErrors();
        $this->assertSame('draft', $this->framework()->framework_status);
        $this->approveFramework()->assertSessionHasErrors('framework');

        // The withdrawn uncertainty stays the framework's axis until a replacement is approved.
        $this->actingAs($this->manager)->delete(route('scenario-planning.uncertainties.destroy', [$this->project, $regulation]))->assertSessionHasErrors('name');

        $this->propose(['Politic'], ['name' => 'Policy Direction', 'low_pole_label' => 'Restrictive', 'high_pole_label' => 'Supportive'])->assertSessionHasNoErrors();
        $this->approve(CriticalUncertainty::where('name', 'Policy Direction')->sole(), 'A')->assertSessionHasNoErrors();

        $framework = $this->framework();
        $this->assertSame(CriticalUncertainty::where('name', 'Policy Direction')->value('id'), $framework->axis_a_uncertainty_id);
        $this->assertSame(['Restrictive', 'Supportive'], [$framework->axis_a_low_label, $framework->axis_a_high_label]);
        $this->assertSame('Supportive · Fast', $this->quadrant('HH')->title);
        $this->actingAs($this->manager)->delete(route('scenario-planning.uncertainties.destroy', [$this->project, $regulation]))->assertSessionHasNoErrors();
    }

    public function test_the_framework_is_managed_in_the_exercise_and_narratives_wait_for_approval(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $framework = $this->framework();

        $this->actingAs($this->manager)->putJson("/scenarios/{$framework->uuid}", [
            'name' => 'X', 'axis_a' => $this->forces['Politic']->uuid, 'axis_b' => $this->forces['Technology']->uuid,
        ] + $this->poles())->assertStatus(422)->assertJsonValidationErrors('framework');

        $this->actingAs($this->manager)->postJson("/scenarios/{$framework->uuid}/generate")->assertStatus(422)->assertJsonValidationErrors('framework');
    }

    public function test_generation_fills_the_existing_quadrants_and_keeps_approved_names(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->actingAs($this->manager)->put(route('scenario-planning.framework.quadrant', [$this->project, $this->quadrant('LL')]), ['title' => 'Jalan di tempat', 'summary' => 'Ringkas.']);
        $this->approveFramework();
        $ids = $this->framework()->quadrants()->pluck('id', 'position')->all();

        // No LLM key in tests: the queued job runs synchronously and writes the template.
        $this->actingAs($this->manager)->postJson("/scenarios/{$this->framework()->uuid}/generate")->assertStatus(202);

        $quadrants = $this->framework()->quadrants()->get()->keyBy('position');
        $this->assertSame($ids, $quadrants->pluck('id', 'position')->all());
        $this->assertSame('Jalan di tempat', $quadrants['LL']->title);
        $this->assertSame('Ringkas.', $quadrants['LL']->summary);
        $this->assertStringContainsString('Government Regulation', $quadrants['LL']->narrative);
        $this->actingAs($this->manager)->getJson("/scenarios/{$this->framework()->uuid}")
            ->assertJsonPath('axis_a.keyword', 'Government Regulation')
            ->assertJsonPath('project.name', $this->project->name);
    }

    public function test_hard_deleting_a_force_used_by_an_approved_framework_is_refused(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->approveFramework();

        try {
            $this->forces['Politic']->forceDelete();
            $this->fail('The force was deleted.');
        } catch (ValidationException) {
            $this->assertNotNull(DrivingForce::withTrashed()->find($this->forces['Politic']->id));
        }

        $this->forces['Purchasing Power']->forceDelete();
        $this->assertNull(DrivingForce::withTrashed()->find($this->forces['Purchasing Power']->id));
    }
}
