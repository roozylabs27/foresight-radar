<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\BackcastingMilestone;
use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\ProjectDecision;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
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
 * Roadmap R4.1 (deck slides 2 and 26): once the framework is approved, the coordinator marks one
 * quadrant as the preferred future with a rationale and lists milestones back from the target year
 * to now. Each milestone may point at a step 8 response and a step 9 indicator. Choosing or clearing
 * the preferred future is logged as a decision; the report ends with the path.
 */
class BackcastingTest extends TestCase
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

    private function addItem(string $position, array $data = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.implications.store', [$this->project, $this->quadrant($position)]), $data + [
            'type' => 'opportunity',
            'title' => 'Ekspor komponen baterai',
        ]);
    }

    private function condition(string $position, string $text): ScenarioCondition
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant($position)]), ['kind' => 'assumption', 'text' => $text])->assertSessionHasNoErrors();

        return ScenarioCondition::where('text', $text)->sole();
    }

    private function response(string $title, string $status, array $fit): StrategicResponse
    {
        $response = StrategicResponse::create([
            'tenant_id' => $this->project->tenant_id, 'uuid' => (string) Uuid::uuid4(), 'project_id' => $this->project->id,
            'title' => $title, 'status' => $status, 'origin' => 'human',
        ]);
        foreach ($fit as $position => $value) {
            $response->fits()->create(['scenario_quadrant_id' => $this->quadrant($position)->id, 'fit' => $value]);
        }

        return $response;
    }

    private function indicator(string $name, array $directions, array $attributes = []): EarlyWarningIndicator
    {
        $indicator = EarlyWarningIndicator::create($attributes + [
            'tenant_id' => $this->project->tenant_id, 'uuid' => (string) Uuid::uuid4(), 'project_id' => $this->project->id, 'name' => $name,
        ]);
        foreach ($directions as $position => $direction) {
            $indicator->directions()->create(['scenario_quadrant_id' => $this->quadrant($position)->id, 'expected_direction' => $direction]);
        }

        return $indicator;
    }

    private function prefer(string $position, array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->post(route('scenario-planning.backcasting.preferred', $this->project), $overrides + [
            'quadrant' => $this->quadrant($position)->uuid,
            'rationale' => 'Sejalan dengan target emisi dan memberi ruang untuk semua teknologi xEV.',
        ]);
    }

    private function milestone(array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->post(route('scenario-planning.backcasting.milestones.store', $this->project), $overrides + [
            'year' => $this->project->target_year - 2,
            'title' => 'Insentif untuk semua xEV terbit',
        ]);
    }

    public function test_the_coordinator_picks_the_preferred_future_and_lists_milestones_back_to_now(): void
    {
        $this->readyFramework();
        $response = $this->response('Advokasi insentif xEV', 'approved', []);
        $indicator = $this->indicator('Insentif semua xEV', ['HH' => 'rising'], ['status' => 'active', 'review_status' => 'accepted']);

        $this->prefer('HH')->assertSessionHasNoErrors();
        $project = $this->project->fresh();
        $this->assertSame($this->quadrant('HH')->id, $project->preferred_quadrant_id);
        $this->assertSame($this->manager->id, $project->preferred_by);
        $this->assertNotNull($project->preferred_at);
        $this->assertSame(['PF', 'approved'], [ProjectDecision::latest('id')->value('gate'), ProjectDecision::latest('id')->value('action')]);

        $this->milestone([
            'description' => 'Peraturan insentif mencakup HEV, PHEV, BEV dan FCEV.', 'owner' => $this->manager->uuid,
            'response' => $response->uuid, 'indicator' => $indicator->uuid,
        ])->assertSessionHasNoErrors();
        $this->milestone(['year' => now()->year, 'title' => 'Proposal pasar HEV ke prinsipal'])->assertSessionHasNoErrors();
        $this->milestone(['year' => $this->project->target_year, 'title' => 'xEV arus utama'])->assertSessionHasNoErrors();

        $first = BackcastingMilestone::where('title', 'Insentif untuk semua xEV terbit')->sole();
        $this->assertSame(['planned', $response->id, $indicator->id, $this->manager->id], [$first->status, $first->strategic_response_id, $first->early_warning_indicator_id, $first->owner_id]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.backcasting.index', $this->project))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Backcasting')
                ->where('preferred.position', 'HH')
                ->where('preferred.rationale', 'Sejalan dengan target emisi dan memberi ruang untuk semua teknologi xEV.')
                ->where('preferred.chosen_by', $this->manager->name)
                ->count('quadrants', 4)
                ->count('milestones', 3)
                ->where('milestones.0.title', 'xEV arus utama')
                ->where('milestones.1.title', 'Insentif untuk semua xEV terbit')
                ->where('milestones.1.response.title', 'Advokasi insentif xEV')
                ->where('milestones.1.indicator.name', 'Insentif semua xEV')
                ->where('milestones.2.title', 'Proposal pasar HEV ke prinsipal')
                ->where('canManage', false));

        $this->actingAs($this->manager)->put(route('scenario-planning.backcasting.milestones.update', [$this->project, $first]), [
            'year' => $first->year, 'title' => $first->title, 'status' => 'done',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['done', null], [$first->fresh()->status, $first->fresh()->strategic_response_id]);
        $this->actingAs($this->manager)->delete(route('scenario-planning.backcasting.milestones.destroy', [$this->project, $first]))->assertSessionHasNoErrors();
        $this->assertSame(2, BackcastingMilestone::count());

        // Another preferred future keeps the path; clearing it is logged as withdrawn.
        $this->prefer('LH')->assertSessionHasNoErrors();
        $this->assertSame($this->quadrant('LH')->id, $this->project->fresh()->preferred_quadrant_id);
        $this->assertSame(2, BackcastingMilestone::count());
        $this->actingAs($this->manager)->delete(route('scenario-planning.backcasting.preferred.clear', $this->project))->assertSessionHasNoErrors();
        $this->assertNull($this->project->fresh()->preferred_quadrant_id);
        $this->assertSame(['approved', 'approved', 'withdrawn'], ProjectDecision::where('gate', 'PF')->orderBy('id')->pluck('action')->all());
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('decisions.0.gate', 'PF')->where('decisions.0.label', 'Masa depan pilihan'));
    }

    public function test_the_rules_of_the_preferred_future_and_milestones(): void
    {
        // Only after the framework is approved (G5).
        $this->approveAxes();
        $this->createFramework();
        $this->prefer('HH')->assertSessionHasErrors('backcasting');
        $this->approveFramework()->assertSessionHasNoErrors();

        $this->prefer('HH', [], $this->viewer)->assertForbidden();
        $this->prefer('HH', ['rationale' => ''])->assertSessionHasErrors('rationale');
        $this->prefer('HH', ['quadrant' => (string) Uuid::uuid4()])->assertSessionHasErrors('quadrant');

        // Milestones follow the preferred future and stay between now and the target year.
        $this->milestone()->assertSessionHasErrors('backcasting');
        $this->prefer('HH')->assertSessionHasNoErrors();
        $this->milestone(['year' => now()->year - 1])->assertSessionHasErrors('year');
        $this->milestone(['year' => $this->project->target_year + 1])->assertSessionHasErrors('year');
        $this->milestone(['title' => ''])->assertSessionHasErrors('title');
        $rejected = $this->response('Tunggu dan lihat', 'rejected', []);
        $this->milestone(['response' => $rejected->uuid])->assertSessionHasErrors('response');
        $this->milestone(['indicator' => (string) Uuid::uuid4()])->assertSessionHasErrors('indicator');
        $this->milestone([], $this->viewer)->assertForbidden();
        $this->assertSame(0, BackcastingMilestone::count());
    }

    public function test_the_report_ends_with_the_preferred_future_and_its_path(): void
    {
        $this->readyFramework();
        $this->actingAs($this->viewer)->get(route('scenario-planning.report', $this->project))
            ->assertInertia(fn ($page) => $page->where('backcasting.preferred', null)->where('backcasting.milestones', []));

        $this->prefer('HH')->assertSessionHasNoErrors();
        $this->milestone()->assertSessionHasNoErrors();
        $this->actingAs($this->viewer)->get(route('scenario-planning.report', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('backcasting.preferred.position', 'HH')
                ->where('backcasting.milestones.0.title', 'Insentif untuk semua xEV terbit')
                ->where('backcasting.milestones.0.status', 'planned'));
    }
}
