<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R3.6: an indicator monitored from an external source (first connector: World Bank API)
 * gets its values as proposed observations with provenance (source, series, period, fetch time).
 * They count only once a person confirms them. A coordinator fetches with a button; a daily command
 * fetches every active external indicator. Tests never call the real API (Http::fake).
 */
class ExternalIndicatorSourceTest extends TestCase
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

    private function external(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Inflasi Indonesia', 'source_type' => 'external', 'metric' => 'Inflasi harga konsumen', 'unit' => '%',
            'external_source' => 'world_bank', 'external_series' => 'FP.CPI.TOTL.ZG', 'external_area' => 'IDN',
        ];
    }

    /** World Bank API v2 JSON: a paging header, then the rows newest first. */
    private function worldBank(array $rows): array
    {
        return [
            ['page' => 1, 'pages' => 1, 'per_page' => 10, 'total' => count($rows), 'lastupdated' => '2026-10-15'],
            array_map(fn ($value, $year) => [
                'indicator' => ['id' => 'FP.CPI.TOTL.ZG', 'value' => 'Inflation, consumer prices (annual %)'],
                'country' => ['id' => 'ID', 'value' => 'Indonesia'], 'countryiso3code' => 'IDN',
                'date' => (string) $year, 'value' => $value, 'unit' => '', 'obs_status' => '', 'decimal' => 1,
            ], array_values($rows), array_keys($rows)),
        ];
    }

    private function fetch(EarlyWarningIndicator $indicator, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->post(route('scenario-planning.indicators.fetch', [$this->project, $indicator]));
    }

    public function test_world_bank_values_become_proposed_observations_with_provenance(): void
    {
        // A stub registered first keeps answering, so both fetches are queued up front.
        Http::fake(['api.worldbank.org/*' => Http::sequence()
            ->push($this->worldBank([2024 => 2.3, 2023 => 3.7, 2022 => null, 2021 => 1.6]))
            ->push($this->worldBank([2025 => 2.0, 2024 => 2.3, 2023 => 3.7]))]);
        $this->readyFramework();
        $this->storeIndicator($this->external())->assertSessionHasNoErrors();
        $indicator = EarlyWarningIndicator::sole();

        $this->fetch($indicator)->assertSessionHasNoErrors();

        // No `mrv`: the live API answers 502/503 to it (found when testing against the real API).
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://api.worldbank.org/v2/country/IDN/indicator/FP.CPI.TOTL.ZG?')
            && $request['format'] === 'json' && (int) $request['per_page'] === 20 && ! isset($request['mrv']));
        $rows = IndicatorObservation::orderBy('observed_at')->get();
        $this->assertSame(['2021-12-31', '2023-12-31', '2024-12-31'], $rows->map(fn ($row) => $row->observed_at->toDateString())->all());
        $this->assertSame([1.6, 3.7, 2.3], $rows->pluck('value_numeric')->all());
        $this->assertSame([null, 'rising', 'falling'], $rows->pluck('direction')->all());
        $this->assertSame(['external'], $rows->pluck('origin')->unique()->values()->all());
        $this->assertSame(['proposed'], $rows->pluck('review_status')->unique()->values()->all());
        $this->assertSame([null], $rows->pluck('recorded_by')->unique()->values()->all());
        $latest = $rows->last();
        $this->assertSame(['world_bank', 'FP.CPI.TOTL.ZG/IDN', '2024'], [$latest->source, $latest->source_ref, $latest->source_period]);
        $this->assertNotNull($latest->fetched_at);
        $this->assertSame('completed', $indicator->fresh()->fetch_status);
        $this->assertNotNull($indicator->fresh()->last_fetched_at);

        // A second fetch adds only periods that are new.
        $this->fetch($indicator)->assertSessionHasNoErrors();
        $this->assertSame(4, IndicatorObservation::count());
        $this->assertSame('falling', IndicatorObservation::where('source_period', '2025')->value('direction'));

        // Proposed values do not count until a person confirms one.
        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('indicators.0.external_source', 'world_bank')
                ->where('indicators.0.external_series', 'FP.CPI.TOTL.ZG')
                ->where('indicators.0.external_area', 'IDN')
                ->where('indicators.0.fetch_status', 'completed')
                ->where('indicators.0.observations.0.source_period', '2025')
                ->where('indicators.0.observations.0.source', 'world_bank')
                ->where('indicators.0.observations.0.review_status', 'proposed'));
        $proposal = IndicatorObservation::where('source_period', '2025')->sole();
        $this->actingAs($this->manager)->post(route('scenario-planning.indicators.observations.confirm', [$this->project, $indicator, $proposal]))->assertSessionHasNoErrors();
        $this->assertSame(['accepted', $this->manager->id, 'world_bank'], [$proposal->fresh()->review_status, $proposal->fresh()->recorded_by, $proposal->fresh()->source]);
    }

    public function test_external_settings_are_validated_and_fetching_is_for_coordinators(): void
    {
        Http::fake();
        $this->readyFramework();

        $this->storeIndicator($this->external(['external_source' => '', 'external_series' => '', 'external_area' => '']))
            ->assertSessionHasErrors(['external_source', 'external_series', 'external_area']);
        $this->storeIndicator($this->external(['external_source' => 'bps']))->assertSessionHasErrors('external_source');
        $this->storeIndicator($this->external(['external_series' => 'FP/../CPI']))->assertSessionHasErrors('external_series');
        $this->storeIndicator($this->external(['external_area' => 'ID N']))->assertSessionHasErrors('external_area');
        $this->assertSame(0, EarlyWarningIndicator::count());

        // A manual indicator keeps no external settings and cannot be fetched.
        $this->storeIndicator(['external_source' => 'world_bank', 'external_series' => 'FP.CPI.TOTL.ZG', 'external_area' => 'IDN'])->assertSessionHasNoErrors();
        $manual = EarlyWarningIndicator::sole();
        $this->assertNull($manual->external_source);
        $this->fetch($manual)->assertSessionHasErrors('external');

        $this->storeIndicator($this->external(['external_area' => 'idn']))->assertSessionHasNoErrors();
        $external = EarlyWarningIndicator::where('source_type', 'external')->sole();
        $this->assertSame('IDN', $external->external_area);
        $this->fetch($external, $this->viewer)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_a_failed_fetch_is_recorded_without_observations(): void
    {
        $this->readyFramework();
        $this->storeIndicator($this->external())->assertSessionHasNoErrors();
        $indicator = EarlyWarningIndicator::sole();

        Http::fake(['api.worldbank.org/*' => Http::sequence()
            ->push([['message' => [['id' => '120', 'key' => 'Invalid value', 'value' => 'The provided parameter value is not valid']]]])
            ->push('Server error', 500)
            ->push('Server error', 500)
            ->push('Service unavailable', 503)
            ->push($this->worldBank([2024 => 2.3]))]);
        $this->fetch($indicator)->assertSessionHasNoErrors();
        $this->assertSame('failed', $indicator->fresh()->fetch_status);
        $this->assertStringContainsString('The provided parameter value is not valid', $indicator->fresh()->fetch_error);

        // A server error is tried twice before the fetch fails.
        $this->fetch($indicator)->assertSessionHasNoErrors();
        $this->assertSame('failed', $indicator->fresh()->fetch_status);
        $this->assertStringContainsString('500', $indicator->fresh()->fetch_error);
        $this->assertSame(0, IndicatorObservation::count());

        // One transient 503 followed by data still stores the values.
        $this->fetch($indicator)->assertSessionHasNoErrors();
        $this->assertSame('completed', $indicator->fresh()->fetch_status);
        $this->assertSame(1, IndicatorObservation::count());
    }

    public function test_the_daily_command_fetches_every_active_external_indicator(): void
    {
        Http::fake(['api.worldbank.org/*' => Http::response($this->worldBank([2024 => 2.3]))]);
        $this->readyFramework();
        $this->storeIndicator($this->external())->assertSessionHasNoErrors();
        $this->storeIndicator($this->external(['name' => 'PDB per kapita', 'external_series' => 'NY.GDP.PCAP.CD', 'status' => 'retired']))->assertSessionHasNoErrors();
        $this->storeIndicator(['name' => 'Manual'])->assertSessionHasNoErrors();

        $this->artisan('indicators:fetch-external')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(['Inflasi Indonesia'], IndicatorObservation::with('indicator')->get()->pluck('indicator.name')->all());
        $this->artisan('schedule:list')->expectsOutputToContain('indicators:fetch-external')->assertSuccessful();
    }
}
