<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\CrossImpact;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
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
 * Roadmap R0.4: the team groups exercise forces into named critical
 * uncertainties with two poles and a reason, then approves exactly two as the
 * scenario axes (gate G4). Defaults pending Pak Jaka (client questions 3–5):
 * grouping allowed, predetermined forces excluded, coordinator approves.
 */
class CriticalUncertaintyTest extends TestCase
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

    public function test_an_uncertainty_can_group_several_forces_with_poles_and_a_reason(): void
    {
        $this->propose(['Politic', 'Legislative'])->assertSessionHasNoErrors();

        $uncertainty = CriticalUncertainty::sole();
        $this->assertSame('proposed', $uncertainty->status);
        $this->assertNull($uncertainty->axis);
        $this->assertSame(['Favorable', 'Unfavorable'], [$uncertainty->high_pole_label, $uncertainty->low_pole_label]);
        $this->assertEqualsCanonicalizing(
            [$this->link($this->project, 'Politic')->id, $this->link($this->project, 'Legislative')->id],
            $uncertainty->projectForces()->pluck('project_driving_forces.id')->all()
        );
    }

    public function test_proposals_need_agreed_scores(): void
    {
        $this->project = $this->exercise(agreeScores: false);

        $this->propose(['Politic'])->assertSessionHasErrors('forces');
        $this->assertSame(0, CriticalUncertainty::count());
    }

    public function test_proposals_are_validated(): void
    {
        $this->propose(['Politic'], ['selection_rationale' => ''])->assertSessionHasErrors('selection_rationale');
        $this->propose(['Politic'], ['high_pole_label' => 'Unfavorable'])->assertSessionHasErrors('high_pole_label');
        $this->propose([], ['forces' => []])->assertSessionHasErrors('forces');
        $this->propose(['Purchasing Power'])->assertSessionHasErrors('forces');

        $other = $this->exercise();
        $foreignLink = $this->link($other, 'Politic');
        $this->propose([], ['forces' => [$foreignLink->uuid]])->assertSessionHasErrors('forces');

        $this->assertSame(0, CriticalUncertainty::where('project_id', $this->project->id)->count());
    }

    public function test_exactly_two_are_approved_on_different_axes(): void
    {
        [$regulation, $technology] = $this->twoProposals();
        $this->propose(['Politic'], ['name' => 'Politik saja'])->assertSessionHasNoErrors();
        $third = CriticalUncertainty::where('name', 'Politik saja')->sole();

        $this->approve($regulation, 'A')->assertSessionHasNoErrors();
        $this->approve($technology, 'A')->assertSessionHasErrors('axis');
        $this->approve($technology, 'B')->assertSessionHasNoErrors();
        $this->approve($third, 'B')->assertSessionHasErrors('axis');

        $regulation->refresh();
        $this->assertSame(['approved', 'A', $this->manager->id], [$regulation->status, $regulation->axis, $regulation->approved_by]);
        $this->assertSame('B', $technology->fresh()->axis);
        $this->assertSame('proposed', $third->fresh()->status);
    }

    public function test_strongly_linked_axes_need_an_acknowledgement(): void
    {
        [$regulation, $technology] = $this->twoProposals();
        CrossImpact::create(['source_driving_force_id' => $this->forces['Legislative']->id, 'target_driving_force_id' => $this->forces['Technology']->id, 'score' => 3]);
        $this->approve($regulation, 'A');

        $this->approve($technology, 'B')->assertSessionHasErrors('acknowledge_dependency');
        $this->approve($technology, 'B', acknowledge: true)->assertSessionHasNoErrors();

        $this->assertSame('approved', $technology->fresh()->status);
    }

    public function test_two_approvals_complete_step_three_and_open_step_four(): void
    {
        [$regulation, $technology] = $this->twoProposals();
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('steps.2.status', 'in_progress')->where('steps.3.status', 'locked'));

        $this->approve($regulation, 'A');
        $this->approve($technology, 'B');

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('steps.2.status', 'done')->where('steps.3.status', 'available'));
    }

    public function test_changing_a_score_withdraws_the_approvals(): void
    {
        [$regulation, $technology] = $this->twoProposals();
        $this->approve($regulation, 'A');
        $this->approve($technology, 'B');

        $this->actingAs($this->manager)->put(route('scenario-planning.assessment.update', [$this->project, $this->link($this->project, 'Technology')]), ['impact' => 5, 'uncertainty' => 9]);

        $this->assertSame(['proposed', null], [$regulation->fresh()->status, $regulation->fresh()->axis]);
        $this->assertSame('proposed', $technology->fresh()->status);
    }

    public function test_an_approved_uncertainty_is_locked_until_its_approval_is_withdrawn(): void
    {
        [$regulation] = $this->twoProposals();
        $this->approve($regulation, 'A');
        $route = route('scenario-planning.uncertainties.update', [$this->project, $regulation]);

        $this->actingAs($this->manager)->put($route, $this->payload(['Politic'], ['name' => 'Diubah']))->assertSessionHasErrors('name');
        $this->actingAs($this->manager)->delete(route('scenario-planning.uncertainties.destroy', [$this->project, $regulation]))->assertSessionHasErrors('name');

        $this->actingAs($this->manager)->post(route('scenario-planning.uncertainties.withdraw', [$this->project, $regulation]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->put($route, $this->payload(['Politic'], ['name' => 'Diubah']))->assertSessionHasNoErrors();
        $this->assertSame(['Diubah', 'proposed'], [$regulation->fresh()->name, $regulation->fresh()->status]);
    }

    public function test_viewers_and_non_owners_cannot_change_uncertainties(): void
    {
        [$regulation] = $this->twoProposals();
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);

        $this->actingAs($this->viewer)->post(route('scenario-planning.uncertainties.approve', [$this->project, $regulation]), ['axis' => 'A'])->assertForbidden();
        $this->actingAs($colleague)->post(route('scenario-planning.uncertainties.approve', [$this->project, $regulation]), ['axis' => 'A'])->assertForbidden();
        $this->actingAs($colleague)->post(route('scenario-planning.uncertainties.store', $this->project), $this->payload(['Politic']))->assertForbidden();
        $this->assertSame('proposed', $regulation->fresh()->status);
    }

    public function test_an_uncertainty_of_another_project_is_not_found(): void
    {
        [$regulation] = $this->twoProposals();
        $other = $this->exercise();

        $this->actingAs($this->manager)->post(route('scenario-planning.uncertainties.approve', [$other, $regulation]), ['axis' => 'A'])->assertNotFound();
    }

    public function test_the_page_suggests_pairs_with_their_mutual_influence(): void
    {
        $this->twoProposals();
        CrossImpact::create(['source_driving_force_id' => $this->forces['Technology']->id, 'target_driving_force_id' => $this->forces['Politic']->id, 'score' => -2]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.uncertainties.index', $this->project))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Uncertainties')
                ->has('uncertainties', 2)
                ->has('pairs', 1)
                ->where('pairs.0.influence', 2)
                ->where('pairs.0.dependent', true)
                ->where('uncertainties.0.forces.0.impact', 8));
    }
}
