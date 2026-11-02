<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R1.1: impact and uncertainty (1–10) with rationale are scored per
 * exercise, shown on the exercise's own matrix, and agreed as gate G3.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 3.
 */
class ProjectAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private Dimension $legislative;

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
        $this->legislative = Dimension::where('code', 'LEGISLATIVE')->sole();
    }

    private function project(bool $agreeForces = true, array $forces = []): ScenarioPlanningProject
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Elektrifikasi 2030',
            'focal_question' => 'How Indonesia vehicle electrification look like in 2030?',
            'purpose' => 'Strategic proposal toward 2030.',
            'target_year' => now()->year + 4,
        ]);
        $project = ScenarioPlanningProject::latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project));
        if ($forces !== []) {
            $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $project), ['forces' => array_map(fn ($f) => $f->uuid, $forces)]);
        }
        if ($agreeForces) {
            $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        }

        return $project->fresh();
    }

    private function force(string $keyword, ?array $rating = null): DrivingForce
    {
        $force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $this->legislative->id, 'created_by' => $this->manager->id,
            'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
        ]);
        if ($rating) {
            $horizon = TimeHorizon::firstOrCreate(['code' => 'MT'], ['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term']);
            DrivingForceRating::create(['driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'impact_analysis' => $rating[0], 'uncertainty_analysis' => $rating[1]]);
        }

        return $force;
    }

    private function link(ScenarioPlanningProject $project, DrivingForce $force): ProjectDrivingForce
    {
        return ProjectDrivingForce::where('project_id', $project->id)->where('driving_force_id', $force->id)->sole();
    }

    private function score(ScenarioPlanningProject $project, DrivingForce $force, array $values)
    {
        return $this->actingAs($this->manager)->put(route('scenario-planning.assessment.update', [$project, $this->link($project, $force)]), $values);
    }

    public function test_manager_scores_an_included_force_with_rationale(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(forces: [$force]);

        $this->score($project, $force, [
            'impact' => 9, 'uncertainty' => 8,
            'impact_rationale' => 'Menentukan akses insentif.', 'uncertainty_rationale' => 'Aturan turunan belum terbit.',
        ])->assertSessionHasNoErrors();

        $assessment = ForceAssessment::sole();
        $this->assertSame('consensus', $assessment->kind);
        $this->assertSame('human', $assessment->origin);
        $this->assertSame([9, 8], [$assessment->impact, $assessment->uncertainty]);
        $this->assertSame('Aturan turunan belum terbit.', $assessment->uncertainty_rationale);
        $this->assertSame($this->manager->id, $assessment->updated_by);
    }

    public function test_scores_are_validated_and_need_the_agreed_force_list(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(agreeForces: false, forces: [$force]);

        $this->score($project, $force, ['impact' => 9, 'uncertainty' => 8])->assertSessionHasErrors('impact');
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        $this->score($project, $force, ['impact' => 11, 'uncertainty' => 0])->assertSessionHasErrors(['impact', 'uncertainty']);

        $this->assertSame(0, ForceAssessment::count());
    }

    public function test_an_excluded_force_cannot_be_scored(): void
    {
        $kept = $this->force('TKDN Regulation');
        $dropped = $this->force('Import Regulation');
        $project = $this->project(agreeForces: false, forces: [$kept, $dropped]);
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $dropped)]), ['inclusion_status' => 'excluded']);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));

        $this->score($project, $dropped, ['impact' => 5, 'uncertainty' => 5])->assertSessionHasErrors('impact');
        $this->assertSame(0, ForceAssessment::count());
    }

    public function test_adding_a_force_copies_its_organization_rating_for_review(): void
    {
        $rated = $this->force('TKDN Regulation', [8, 9]);
        $unrated = $this->force('Solid Battery');
        $project = $this->project(forces: [$rated, $unrated]);

        $assessment = ForceAssessment::sole();
        $this->assertSame($this->link($project, $rated)->id, $assessment->project_driving_force_id);
        $this->assertSame('library', $assessment->origin);
        $this->assertSame([8, 9], [$assessment->impact, $assessment->uncertainty]);
    }

    public function test_project_scores_never_change_the_organization_rating(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $project = $this->project(forces: [$force]);

        $this->score($project, $force, ['impact' => 3, 'uncertainty' => 2])->assertSessionHasNoErrors();

        $rating = DrivingForceRating::where('driving_force_id', $force->id)->sole();
        $this->assertSame([8, 9], [$rating->impact_analysis, $rating->uncertainty_analysis]);
        $this->assertSame('human', ForceAssessment::sole()->origin);
    }

    public function test_the_page_plots_only_included_forces_numbered_in_method_order(): void
    {
        $a = $this->force('Import Regulation', [6, 4]);
        $b = $this->force('TKDN Regulation', [8, 9]);
        $c = $this->force('Euro-5 Plan');
        $project = $this->project(agreeForces: false, forces: [$a, $b, $c]);
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $c)]), ['inclusion_status' => 'excluded']);

        $this->actingAs($this->viewer)->get(route('scenario-planning.assessment.index', $project))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Assessment')
                ->has('forces', 2)
                ->where('forces.0.number', 1)
                ->where('forces.0.force.keyword', 'Import Regulation')
                ->where('forces.1.impact', 8)
                ->where('forces.1.uncertainty', 9)
                ->where('forces.1.origin', 'library'));
    }

    public function test_agreeing_scores_needs_every_included_force_scored(): void
    {
        $scored = $this->force('TKDN Regulation', [8, 9]);
        $unscored = $this->force('Solid Battery');
        $project = $this->project(forces: [$scored, $unscored]);

        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project))->assertSessionHasErrors('scores');
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.2.status', 'in_progress')->where('steps.3.status', 'locked'));

        $this->score($project, $unscored, ['impact' => 7, 'uncertainty' => 8]);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project))->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame($this->manager->id, $project->scores_confirmed_by);
        // Step 3 finishes, and step 4 opens, only when two critical uncertainties are approved (R0.4).
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.2.status', 'in_progress')->where('steps.3.status', 'locked'));
    }

    public function test_changing_a_score_after_agreement_withdraws_it_but_a_rationale_edit_does_not(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $project = $this->project(forces: [$force]);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project));

        $this->score($project, $force, ['impact' => 8, 'uncertainty' => 9, 'impact_rationale' => 'Lebih jelas.']);
        $this->assertNotNull($project->fresh()->scores_confirmed_at);

        $this->score($project, $force, ['impact' => 6, 'uncertainty' => 9])->assertSessionHas('success');
        $this->assertNull($project->fresh()->scores_confirmed_at);
    }

    public function test_withdrawing_the_force_list_agreement_also_withdraws_the_score_agreement(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $other = $this->force('Solid Battery', [5, 5]);
        $project = $this->project(forces: [$force, $other]);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project));

        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $other)]), ['inclusion_status' => 'excluded']);

        $project->refresh();
        $this->assertNull($project->forces_confirmed_at);
        $this->assertNull($project->scores_confirmed_at);
    }

    public function test_viewers_and_non_owners_cannot_score(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(forces: [$force]);
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);
        $link = $this->link($project, $force);

        $this->actingAs($this->viewer)->put(route('scenario-planning.assessment.update', [$project, $link]), ['impact' => 5, 'uncertainty' => 5])->assertForbidden();
        $this->actingAs($colleague)->put(route('scenario-planning.assessment.update', [$project, $link]), ['impact' => 5, 'uncertainty' => 5])->assertForbidden();
        $this->assertSame(0, ForceAssessment::count());
    }
}
