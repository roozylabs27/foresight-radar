<?php

namespace Tests\Feature\Reports;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Priority;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\StatusAction;
use App\Models\Tenant;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The three firm reports (Foresight Radar, Prioritizing, Registered List) show the whole firm by
 * default; `scope=<exercise uuid>` narrows them to one Key Focal Issue: its included forces with
 * the exercise consensus scores, priority recalculated from them, horizon and status action from
 * the firm rating, and no period filter.
 */
class ReportScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $reportViewer;

    private ScenarioPlanningProject $project;

    /** @var array<string, DrivingForce> */
    private array $forces = [];

    private TimeHorizon $shortTerm;

    private TimeHorizon $midTerm;

    private StatusAction $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        $reports = ['view-foresight-radar', 'view-prioritizing', 'view-registered-list', 'export-registered-list'];
        foreach ([...$reports, 'view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'report', 'description' => $name]);
        }
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo([...$reports, 'view-scenario', 'manage-scenario']);
        $this->reportViewer = User::factory()->create(['tenant_id' => $this->manager->tenant_id]);
        $this->reportViewer->givePermissionTo($reports);

        DimensionTaxonomy::align();
        $this->shortTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Short Term', 'code' => 'ST']);
        $this->midTerm = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        foreach (['High' => '#ff0000', 'Medium' => '#ffaa00', 'Low' => '#00aa00'] as $name => $color) {
            Priority::create(['uuid' => (string) Uuid::uuid4(), 'name' => $name, 'color' => $color]);
        }
        $this->monitor = StatusAction::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Monitor', 'symbol' => 'M', 'code' => 'ACT-M']);

        // keyword => [dimension code, firm rating [horizon, impact, uncertainty] or null]
        $library = [
            'TKDN Regulation' => ['LEGISLATIVE', [$this->shortTerm, 8, 8]],
            'LFP Battery' => ['TECHNOLOGY', [$this->midTerm, 4, 4]],
            'Interest Rate' => ['ECONOMIC', [$this->midTerm, 3, 3]],
            'Euro-5 Plan' => ['LEGISLATIVE', [$this->midTerm, 7, 7]],
            'Mobility Sharing' => ['CONSUMER', null],
            'Loan Tenure' => ['ECONOMIC', null],
        ];
        foreach ($library as $keyword => [$code, $rating]) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', $code)->value('id'),
                'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
                'created_by' => $this->manager->id, 'pic' => $this->manager->id,
            ]);
            if ($rating) {
                [$horizon, $impact, $uncertainty] = $rating;
                DrivingForceRating::create([
                    'driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'status_action_id' => $this->monitor->id,
                    'priority_id' => Priority::where('name', $impact >= 6 && $uncertainty >= 6 ? 'High' : 'Low')->value('id'),
                    'impact_analysis' => $impact, 'uncertainty_analysis' => $uncertainty,
                ]);
            }
            $this->forces[$keyword] = $force;
        }

        $this->project = $this->exercise();
    }

    /**
     * The exercise: TKDN (consensus copied 8/8), LFP (consensus changed to 9/7), Mobility Sharing (no firm
     * rating, consensus 6/2), Loan Tenure (no rating, never scored), Euro-5 Plan (excluded). Interest Rate is
     * not in the exercise.
     */
    private function exercise(): ScenarioPlanningProject
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Elektrifikasi 2030', 'focal_question' => 'Seperti apa elektrifikasi kendaraan di Indonesia pada 2030?',
            'purpose' => 'Uji cakupan laporan.', 'target_year' => now()->year + 4,
        ])->assertSessionHasNoErrors();
        $project = ScenarioPlanningProject::latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project))->assertSessionHasNoErrors();
        $keywords = ['TKDN Regulation', 'LFP Battery', 'Mobility Sharing', 'Loan Tenure', 'Euro-5 Plan'];
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $project), ['forces' => array_map(fn ($k) => $this->forces[$k]->uuid, $keywords)])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, 'Euro-5 Plan')]), ['inclusion_status' => 'excluded'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project))->assertSessionHasNoErrors();
        $this->score($project, 'LFP Battery', 9, 7);
        $this->score($project, 'Mobility Sharing', 6, 2);

        return $project->fresh();
    }

    private function link(ScenarioPlanningProject $project, string $keyword): ProjectDrivingForce
    {
        return ProjectDrivingForce::where('project_id', $project->id)->where('driving_force_id', $this->forces[$keyword]->id)->sole();
    }

    private function score(ScenarioPlanningProject $project, string $keyword, int $impact, int $uncertainty): void
    {
        $this->actingAs($this->manager)->put(route('scenario-planning.assessment.update', [$project, $this->link($project, $keyword)]), ['impact' => $impact, 'uncertainty' => $uncertainty])
            ->assertSessionHasNoErrors();
    }

    private function data(string $route, array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->getJson(route($route, $query));
    }

    /** @return array<string, array<string, mixed>> rows keyed by keyword */
    private function rows($response): array
    {
        $rows = $response->json('data') ?? $response->json();

        return collect($rows)->keyBy('keyword')->all();
    }

    public function test_the_firm_view_stays_the_default(): void
    {
        $firm = $this->rows($this->data('visualization.prioritizing.get-data')->assertOk());

        $this->assertEqualsCanonicalizing(['TKDN Regulation', 'LFP Battery', 'Interest Rate', 'Euro-5 Plan'], array_keys($firm));
        $this->assertSame([4, 4], [$firm['LFP Battery']['impact_analysis'], $firm['LFP Battery']['uncertainty_analysis']]);
        $this->assertSame($firm, $this->rows($this->data('visualization.prioritizing.get-data', ['scope' => 'firm'])->assertOk()));
    }

    public function test_a_key_focal_issue_shows_its_included_forces_with_the_exercise_consensus(): void
    {
        $response = $this->data('visualization.prioritizing.get-data', ['scope' => $this->project->uuid])->assertOk();
        $rows = $this->rows($response);

        // Excluded (Euro-5), outside the exercise (Interest Rate) and never scored (Loan Tenure) are left out.
        $this->assertEqualsCanonicalizing(['TKDN Regulation', 'LFP Battery', 'Mobility Sharing'], array_keys($rows));
        $this->assertSame([9, 7, 'High'], [$rows['LFP Battery']['impact_analysis'], $rows['LFP Battery']['uncertainty_analysis'], $rows['LFP Battery']['priority']]);
        $this->assertSame([8, 8, 'High', 'Short Term'], [$rows['TKDN Regulation']['impact_analysis'], $rows['TKDN Regulation']['uncertainty_analysis'], $rows['TKDN Regulation']['priority'], $rows['TKDN Regulation']['time_horizon']]);
        // No firm rating: the consensus is plotted, without horizon or status action.
        $this->assertSame([6, 2, 'Medium', null, null], [
            $rows['Mobility Sharing']['impact_analysis'], $rows['Mobility Sharing']['uncertainty_analysis'], $rows['Mobility Sharing']['priority'],
            $rows['Mobility Sharing']['time_horizon'], $rows['Mobility Sharing']['status_action'],
        ]);
        $response->assertHeader('X-Report-Skipped', '1');

        // The firm rating itself is never changed.
        $rating = DrivingForceRating::where('driving_force_id', $this->forces['LFP Battery']->id)->sole();
        $this->assertSame([4, 4], [$rating->impact_analysis, $rating->uncertainty_analysis]);
    }

    public function test_the_radar_needs_a_horizon_and_ignores_the_period(): void
    {
        $longAgo = ['date' => ['2020-01-01', '2020-01-31']];

        $response = $this->data('visualization.foresight-radar.get-data', $longAgo + ['scope' => $this->project->uuid])->assertOk();
        $rows = $this->rows($response);

        $this->assertEqualsCanonicalizing(['TKDN Regulation', 'LFP Battery'], array_keys($rows));
        $this->assertSame(9, $rows['LFP Battery']['impact_analysis']);
        // Loan Tenure is unscored and Mobility Sharing has no horizon.
        $response->assertHeader('X-Report-Skipped', '2');

        $this->assertSame([], $this->rows($this->data('visualization.foresight-radar.get-data', $longAgo)->assertOk()));
    }

    public function test_the_dimension_filter_applies_to_a_key_focal_issue(): void
    {
        $legislative = Dimension::where('code', 'LEGISLATIVE')->value('id');

        $rows = $this->rows($this->data('visualization.prioritizing.get-data', ['scope' => $this->project->uuid, 'dimension' => $legislative])->assertOk());

        $this->assertSame(['TKDN Regulation'], array_keys($rows));
    }

    public function test_the_registered_list_and_its_export_follow_the_scope(): void
    {
        $page = $this->data('visualization.registered-list.get-data', ['scope' => $this->project->uuid, 'pageSize' => 2])->assertOk();
        $this->assertSame(3, $page->json('meta.total'));
        $this->assertCount(2, $page->json('data'));

        $highOnly = $this->data('visualization.registered-list.get-data', ['scope' => $this->project->uuid, 'priority' => Priority::where('name', 'High')->value('id')])->assertOk();
        $this->assertEqualsCanonicalizing(['TKDN Regulation', 'LFP Battery'], collect($highOnly->json('data'))->pluck('keyword')->all());

        $export = $this->actingAs($this->manager)->get(route('visualization.registered-list.export-data', ['scope' => $this->project->uuid]))->assertOk();
        $this->assertEqualsCanonicalizing(['TKDN Regulation', 'LFP Battery', 'Mobility Sharing'], collect(json_decode($export->streamedContent(), true))->pluck('keyword')->all());
    }

    public function test_the_scope_needs_view_scenario_and_an_exercise_of_the_tenant(): void
    {
        $this->data('visualization.prioritizing.get-data', ['scope' => $this->project->uuid], $this->reportViewer)->assertForbidden();
        $this->data('visualization.prioritizing.get-data', [], $this->reportViewer)->assertOk();
        $this->data('visualization.prioritizing.get-data', ['scope' => (string) Uuid::uuid4()])->assertNotFound();

        $other = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'status' => 'active', 'max_users' => 15]);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);
        $outsider->givePermissionTo(['view-prioritizing', 'view-scenario']);
        $this->data('visualization.prioritizing.get-data', ['scope' => $this->project->uuid], $outsider)->assertNotFound();
    }

    /** The Dashboard: Overview and the what-if simulation use the exercise; month trends stay firm-only. */
    public function test_the_dashboard_overview_and_simulation_follow_the_scope(): void
    {
        Permission::firstOrCreate(['name' => 'view-dashboard', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'report', 'description' => 'view-dashboard']);
        $this->manager->givePermissionTo('view-dashboard');

        $this->actingAs($this->manager)->get('/dashboard')->assertInertia(fn ($page) => $page->has('scopes', 1)->where('scope', 'firm'));

        $firm = $this->actingAs($this->manager)->getJson(route('dashboard.reasoning-data'))->assertOk();
        $this->assertSame(4, $firm->json('total_active_forces'));
        $this->assertNotNull($firm->json('eda_trend_range'));

        $exercise = $this->actingAs($this->manager)->getJson(route('dashboard.reasoning-data', ['scope' => $this->project->uuid]))->assertOk();
        $this->assertSame(3, $exercise->json('total_active_forces'));
        $this->assertSame('exercise', $exercise->json('scope'));
        $this->assertSame([], $exercise->json('eda_trend'));
        $this->assertNull($exercise->json('history_trend'));
        $this->assertSame(['LFP Battery', 'Mobility Sharing', 'TKDN Regulation'], collect($exercise->json('top_critical_forces'))->pluck('keyword')->sort()->values()->all());

        // The simulation works on the exercise's forces: one the firm never rated is in, one outside the exercise is not.
        $simulate = fn (string $keyword, array $scope = []) => $this->actingAs($this->manager)->postJson(route('dashboard.simulate'), $scope + [
            'overrides' => [['force' => $this->forces[$keyword]->uuid, 'impact' => 2]],
        ]);
        $simulate('Mobility Sharing', ['scope' => $this->project->uuid])->assertOk()->assertJsonPath('overrides.0.keyword', 'Mobility Sharing');
        $simulate('Interest Rate', ['scope' => $this->project->uuid])->assertStatus(422)->assertJsonValidationErrors('overrides');
        $simulate('Mobility Sharing')->assertStatus(422)->assertJsonValidationErrors('overrides');

        $this->actingAs($this->reportViewer)->getJson(route('dashboard.reasoning-data', ['scope' => $this->project->uuid]))->assertForbidden();
    }

    public function test_the_report_pages_list_the_key_focal_issues_for_scenario_viewers_only(): void
    {
        $this->actingAs($this->manager)->get('/visualization/prioritizing')
            ->assertInertia(fn ($page) => $page
                ->has('scopes', 1)
                ->where('scopes.0.id', $this->project->uuid)
                ->where('scopes.0.name', 'Elektrifikasi 2030')
                ->where('scopes.0.scores_confirmed', false));

        foreach (['/visualization/foresight-radar', '/visualization/registered-list'] as $path) {
            $this->actingAs($this->manager)->get($path)->assertInertia(fn ($page) => $page->has('scopes', 1));
            $this->actingAs($this->reportViewer)->get($path)->assertInertia(fn ($page) => $page->where('scopes', []));
        }
    }
}
