<?php

namespace Tests\Feature\Journeys;

use App\Enums\LlmJobStatus;
use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Source;
use App\Models\StrategicResponse;
use App\Models\Tenant;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Audit 2026-11-11 U7 (Scenario B): two organisations. Organisation A runs an exercise through every
 * step; a member of organisation B with the same permissions guesses A's ids in URLs and API calls
 * and gets "not found" every time, sees none of A's work in lists, and changes nothing.
 * Report scope and dimension settings have their own cross-organisation tests (ReportScopeTest,
 * DimensionSettingsTest, TenantDimensionsTest).
 */
class TenantIsolationJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['view-scenario', 'manage-scenario', 'view-signal', 'create-signal', 'view-dashboard'];

    private User $memberA;

    private User $memberB;

    private ScenarioPlanningProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'test', 'description' => $name]);
        }
        $tenantA = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        $this->memberA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $this->memberB = User::factory()->create(['tenant_id' => $tenantB->id]);
        foreach ([$this->memberA, $this->memberB] as $member) {
            $member->givePermissionTo(self::PERMISSIONS);
        }

        // Organisation A's firm library.
        app(TenantContext::class)->setTenantId($tenantA->id);
        $horizon = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        foreach (['Politic' => ['POLITICAL', 8, 8], 'Technology' => ['TECHNOLOGY', 8, 9], 'Purchasing Power' => ['CONSUMER', 7, 4]] as $keyword => [$code, $impact, $uncertainty]) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', $code)->value('id'), 'created_by' => $this->memberA->id,
                'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
            ]);
            DrivingForceRating::create(['driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'impact_analysis' => $impact, 'uncertainty_analysis' => $uncertainty]);
        }
        app(TenantContext::class)->setTenantId(null);

        $this->project = $this->exerciseOfA();
    }

    private function asA()
    {
        return $this->actingAs($this->memberA);
    }

    private function asB()
    {
        return $this->actingAs($this->memberB);
    }

    private function link(string $keyword): ProjectDrivingForce
    {
        return ProjectDrivingForce::withoutGlobalScopes()->where('project_id', $this->project->id)
            ->whereHas('drivingForce', fn ($q) => $q->withoutGlobalScopes()->where('keyword', $keyword))->sole();
    }

    private function quadrant(string $position): ScenarioQuadrant
    {
        $framework = Scenario::withoutGlobalScopes()->where('project_id', $this->project->id)->sole();

        return ScenarioQuadrant::withoutGlobalScopes()->where('scenario_id', $framework->id)->where('position', $position)->sole();
    }

    /** Organisation A's exercise through G5, with an implication, a response, an indicator with an observation, and a failed source. */
    private function exerciseOfA(): ScenarioPlanningProject
    {
        $this->asA()->post(route('scenario-planning.store'), [
            'name' => 'Rahasia Acme', 'focal_question' => 'How will Acme grow by 2030?', 'purpose' => 'Confidential.', 'target_year' => now()->year + 4,
        ])->assertSessionHasNoErrors();
        $this->project = ScenarioPlanningProject::withoutGlobalScopes()->where('name', 'Rahasia Acme')->sole();
        $this->asA()->post(route('scenario-planning.confirm', $this->project))->assertSessionHasNoErrors();
        $forces = DrivingForce::withoutGlobalScopes()->where('tenant_id', $this->memberA->tenant_id)->pluck('uuid')->all();
        $this->asA()->post(route('scenario-planning.forces.store', $this->project), ['forces' => $forces])->assertSessionHasNoErrors();
        $this->asA()->post(route('scenario-planning.forces.confirm', $this->project))->assertSessionHasNoErrors();
        $this->asA()->post(route('scenario-planning.assessment.confirm', $this->project))->assertSessionHasNoErrors();
        foreach ([['Politic', 'Regulation', 'A'], ['Technology', 'Technology', 'B']] as [$keyword, $name, $axis]) {
            $this->asA()->post(route('scenario-planning.uncertainties.store', $this->project), [
                'name' => $name, 'description' => "{$name}.", 'forces' => [$this->link($keyword)->uuid],
                'low_pole_label' => 'Low', 'high_pole_label' => 'High', 'selection_rationale' => 'Highest scores.',
            ])->assertSessionHasNoErrors();
            $uncertainty = CriticalUncertainty::withoutGlobalScopes()->where('project_id', $this->project->id)->where('name', $name)->sole();
            $this->asA()->post(route('scenario-planning.uncertainties.approve', [$this->project, $uncertainty]), ['axis' => $axis])->assertSessionHasNoErrors();
        }
        $this->asA()->post(route('scenario-planning.framework.store', $this->project))->assertSessionHasNoErrors();
        $this->asA()->post(route('scenario-planning.framework.approve', $this->project))->assertSessionHasNoErrors();

        $this->asA()->post(route('scenario-planning.implications.store', [$this->project, $this->quadrant('HH')]), ['type' => 'opportunity', 'title' => 'Ekspansi rahasia'])->assertSessionHasNoErrors();
        $this->asA()->post(route('scenario-planning.responses.store', $this->project), [
            'title' => 'Akuisisi rahasia', 'rationale' => 'Confidential.', 'fit' => ['HH' => 'applies', 'HL' => 'applies', 'LH' => 'applies', 'LL' => 'applies'],
        ])->assertSessionHasNoErrors();
        $this->asA()->post(route('scenario-planning.indicators.store', $this->project), [
            'name' => 'Indikator rahasia', 'frequency' => 'quarterly', 'source_type' => 'manual', 'threshold_operator' => 'gt', 'threshold_value' => 10,
            'directions' => ['HH' => ['direction' => 'rising'], 'HL' => ['direction' => 'falling'], 'LH' => ['direction' => 'rising'], 'LL' => ['direction' => 'falling']],
        ])->assertSessionHasNoErrors();
        $indicator = EarlyWarningIndicator::withoutGlobalScopes()->where('project_id', $this->project->id)->sole();
        $this->asA()->post(route('scenario-planning.indicators.observations.store', [$this->project, $indicator]), ['observed_at' => now()->toDateString(), 'value_numeric' => 5])->assertSessionHasNoErrors();

        app(TenantContext::class)->setTenantId($this->memberA->tenant_id);
        Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => 'Laporan rahasia', 'raw_content' => 'Isi rahasia.', 'content_hash' => hash('sha256', 'rahasia'),
            'created_by' => $this->memberA->id, 'extraction_status' => LlmJobStatus::FAILED, 'extraction_error' => 'Gagal.',
        ]);
        app(TenantContext::class)->setTenantId(null);

        return $this->project;
    }

    public function test_another_organisation_cannot_see_or_change_any_step_of_the_exercise(): void
    {
        $project = $this->project;
        $quadrant = $this->quadrant('HH');
        $implication = ScenarioImplication::withoutGlobalScopes()->where('title', 'Ekspansi rahasia')->sole();
        $response = StrategicResponse::withoutGlobalScopes()->where('title', 'Akuisisi rahasia')->sole();
        $indicator = EarlyWarningIndicator::withoutGlobalScopes()->where('name', 'Indikator rahasia')->sole();
        $observation = IndicatorObservation::withoutGlobalScopes()->where('early_warning_indicator_id', $indicator->id)->sole();
        $source = Source::withoutGlobalScopes()->where('title', 'Laporan rahasia')->sole();
        $condition = ['kind' => 'pole_expression', 'axis' => 'A', 'text' => 'Masuk dari luar.'];

        // Pages of every step, by direct URL.
        foreach (['show', 'forces.index', 'assessment.index', 'uncertainties.index', 'framework.index', 'conditions.index', 'narratives.index', 'narratives.state', 'implications.index', 'responses.index', 'indicators.index', 'compare', 'report', 'backcasting.index'] as $page) {
            $this->asB()->get(route("scenario-planning.{$page}", $project))->assertNotFound();
        }

        // Writes on every step, with A's ids.
        $this->asB()->post(route('scenario-planning.confirm', $project))->assertNotFound();
        $this->asB()->post(route('scenario-planning.framework.reopen', $project))->assertNotFound();
        $this->asB()->post(route('scenario-planning.conditions.store', [$project, $quadrant]), $condition)->assertNotFound();
        $this->asB()->postJson(route('scenario-planning.narratives.generate', $project))->assertNotFound();
        $this->asB()->putJson(route('scenario-planning.narratives.quadrant', [$project, $quadrant]), ['title' => 'x', 'narrative' => 'x'])->assertNotFound();
        $this->asB()->postJson(route('scenario-planning.narratives.review', [$project, $quadrant]), ['status' => 'approved'])->assertNotFound();
        $this->asB()->delete(route('scenario-planning.implications.destroy', [$project, $implication]))->assertNotFound();
        $this->asB()->post(route('scenario-planning.responses.approve', [$project, $response]))->assertNotFound();
        $this->asB()->post(route('scenario-planning.indicators.observations.store', [$project, $indicator]), ['observed_at' => now()->toDateString(), 'value_numeric' => 50])->assertNotFound();
        $this->asB()->delete(route('scenario-planning.indicators.observations.destroy', [$project, $indicator, $observation]))->assertNotFound();
        $this->asB()->post(route('scenario-planning.stale.acknowledge', $project))->assertNotFound();

        // Signals: A's source is not B's to read or retry.
        $this->asB()->getJson(route('signals.extraction', $source->uuid))->assertNotFound();
        $this->asB()->postJson(route('signals.extraction.retry', $source->uuid))->assertNotFound();

        // Reports scoped to A's exercise: the page shell offers only B's exercises, and the data refuses A's.
        $this->asB()->get(route('dashboard.', ['scope' => $project->uuid]))->assertOk()
            ->assertInertia(fn ($page) => $page->where('scopes', fn ($rows) => collect($rows)->isEmpty()));
        $this->asB()->getJson(route('dashboard.reasoning-data', ['scope' => $project->uuid]))->assertNotFound();

        // Lists show none of A's work.
        $this->asB()->get(route('scenario-planning.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('projects', fn ($rows) => collect($rows)->isEmpty()));
        $this->asB()->get(route('signals.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('failedExtractions', fn ($rows) => collect($rows)->isEmpty()));

        // Nothing of A changed.
        $this->assertSame('approved', Scenario::withoutGlobalScopes()->where('project_id', $project->id)->value('framework_status'));
        $this->assertSame(0, ScenarioCondition::withoutGlobalScopes()->count());
        $this->assertSame([1, 1, 1], [
            ScenarioImplication::withoutGlobalScopes()->count(),
            IndicatorObservation::withoutGlobalScopes()->count(),
            StrategicResponse::withoutGlobalScopes()->where('status', 'proposed')->count(),
        ]);
        $this->assertSame(LlmJobStatus::FAILED, $source->fresh()->extraction_status);

        // Organisation A still reaches everything.
        $this->asA()->get(route('scenario-planning.narratives.index', $project))->assertOk();
        $this->asA()->get(route('signals.extraction', $source->uuid))->assertOk();
    }
}
