<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\EarlyWarningIndicator;
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
use Tests\Support\KeepsLibraryScores;
use Tests\TestCase;

/**
 * Roadmap R2.8: the four futures side by side. Columns are the quadrants; rows are the summary,
 * accepted conditions, accepted opportunities and challenges, the fit of each response that is not
 * rejected, and the expected direction of each active, accepted indicator. Proposals are left out.
 */
class ScenarioComparisonTest extends TestCase
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

    public function test_the_comparison_shows_the_four_futures_with_accepted_content_only(): void
    {
        $this->readyFramework();
        $this->condition('HH', 'Insentif EV diperpanjang hingga 2030.');
        $this->condition('HH', 'Draf AI yang belum diterima.')->update(['origin' => 'ai', 'review_status' => 'proposed']);
        $this->condition('LL', 'Subsidi BBM tetap besar.');
        $this->addItem('HH', ['type' => 'opportunity', 'title' => 'Ekspor komponen baterai'])->assertSessionHasNoErrors();
        $this->addItem('HH', ['type' => 'challenge', 'title' => 'Kekurangan teknisi'])->assertSessionHasNoErrors();
        $this->addItem('LL', ['type' => 'opportunity', 'title' => 'Usulan AI'])->assertSessionHasNoErrors();
        ScenarioImplication::where('title', 'Usulan AI')->sole()->update(['origin' => 'ai', 'review_status' => 'proposed']);
        $this->response('Bangun pabrik sel baterai', 'approved', ['HH' => 'applies', 'HL' => 'applies', 'LH' => 'applies', 'LL' => 'partial']);
        $this->response('Respons ditolak', 'rejected', ['HH' => 'applies']);
        $this->indicator('Penjualan EV per bulan', ['HH' => 'rising', 'LL' => 'falling']);
        $this->indicator('Indikator pensiun', ['HH' => 'rising'], ['status' => 'retired']);

        $this->actingAs($this->viewer)->get(route('scenario-planning.compare', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Compare')
                ->where('framework.approved', true)
                ->where('quadrants.0.position', 'HH')
                ->where('quadrants.0.poles', ['Favorable', 'Fast'])
                ->where('quadrants.0.conditions', [['kind' => 'assumption', 'text' => 'Insentif EV diperpanjang hingga 2030.']])
                ->where('quadrants.0.opportunities', ['Ekspor komponen baterai'])
                ->where('quadrants.0.challenges', ['Kekurangan teknisi'])
                ->where('quadrants.3.position', 'LL')
                ->where('quadrants.3.conditions', [['kind' => 'assumption', 'text' => 'Subsidi BBM tetap besar.']])
                ->where('quadrants.3.opportunities', [])
                ->has('responses', 1)
                ->where('responses.0.title', 'Bangun pabrik sel baterai')
                ->where('responses.0.robustness', 'robust')
                ->where('responses.0.fit.LL', 'partial')
                ->has('indicators', 1)
                ->where('indicators.0.directions.HH', 'rising')
                ->where('indicators.0.directions.LL', 'falling')
                ->where('indicators.0.discriminates', true));
    }

    public function test_the_comparison_waits_for_a_framework(): void
    {
        $this->actingAs($this->viewer)->get(route('scenario-planning.compare', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Compare')->where('framework', null)->where('quadrants', [])->where('responses', []));
    }

    public function test_the_comparison_needs_view_permission(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('scenario-planning.compare', $this->project))->assertForbidden();
    }

    private function pole(string $position, string $axis, string $text): ScenarioCondition
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant($position)]), ['kind' => 'pole_expression', 'axis' => $axis, 'text' => $text])->assertSessionHasNoErrors();

        return ScenarioCondition::where('text', $text)->sole();
    }

    private function codes(int $index): array
    {
        $codes = [];
        $this->actingAs($this->viewer)->get(route('scenario-planning.compare', $this->project))
            ->assertInertia(function ($page) use ($index, &$codes) {
                $codes = array_column($page->toArray()['props']['quadrants'][$index]['consistency']['checks'], 'code');
            });

        return $codes;
    }

    private function signOff(string $position, array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->post(route('scenario-planning.consistency', [$this->project, $this->quadrant($position)]), $data);
    }

    /** Roadmap R2.8 slice 2: rule checks per quadrant before the team signs off its consistency. */
    public function test_rule_checks_flag_thin_unbalanced_or_overlapping_quadrants(): void
    {
        $this->readyFramework();
        $this->pole('HH', 'A', 'Regulasi mendukung penuh insentif EV.');
        $this->pole('HH', 'B', 'Teknologi baterai berkembang cepat.');
        $this->condition('LL', 'Subsidi BBM tetap besar.');
        $this->pole('HL', 'A', 'Regulasi mendukung penuh insentif EV nasional.');
        $this->pole('HL', 'B', 'Teknologi baterai berkembang lambat sekali.');
        $this->condition('HL', 'Draf AI yang belum diterima.')->update(['origin' => 'ai', 'review_status' => 'proposed']);

        $this->assertSame(['similar'], $this->codes(0));
        $this->assertSame(['pending', 'similar'], $this->codes(1));
        $this->assertSame(['too_few', 'missing_pole', 'missing_pole'], $this->codes(3));
    }

    public function test_the_coordinator_signs_off_consistency_and_warnings_need_a_note(): void
    {
        $this->readyFramework();
        $this->pole('HH', 'A', 'Regulasi mendukung penuh insentif EV.');
        $this->pole('HH', 'B', 'Teknologi baterai berkembang cepat.');

        $this->signOff('HH', ['status' => 'consistent'], $this->viewer)->assertForbidden();
        $this->signOff('HH', ['status' => 'wrong'])->assertSessionHasErrors('status');
        $this->signOff('HH', ['status' => 'consistent'])->assertSessionHasNoErrors();
        $hh = $this->quadrant('HH');
        $this->assertSame('consistent', $hh->consistency_status);
        $this->assertSame($this->manager->id, $hh->consistency_reviewed_by);
        $this->assertNotNull($hh->consistency_reviewed_at);

        $this->signOff('LL', ['status' => 'consistent'])->assertSessionHasErrors('note');
        $this->signOff('LL', ['status' => 'issues'])->assertSessionHasErrors('note');
        $this->signOff('LL', ['status' => 'consistent', 'note' => 'Kondisi lain menyusul di lokakarya.'])->assertSessionHasNoErrors();
        $this->assertSame('Kondisi lain menyusul di lokakarya.', $this->quadrant('LL')->consistency_note);

        $this->actingAs($this->viewer)->get(route('scenario-planning.compare', $this->project))
            ->assertInertia(fn ($page) => $page->where('quadrants.0.consistency.status', 'consistent')
                ->where('quadrants.0.consistency.reviewed_by', $this->manager->name)
                ->where('quadrants.0.consistency.needs_review', false)
                ->where('canManage', false));
    }

    public function test_changing_an_accepted_condition_asks_for_a_new_review(): void
    {
        $this->readyFramework();
        $pole = $this->pole('HH', 'A', 'Regulasi mendukung penuh insentif EV.');
        $this->pole('HH', 'B', 'Teknologi baterai berkembang cepat.');
        $this->signOff('HH', ['status' => 'consistent'])->assertSessionHasNoErrors();

        // A draft that is not accepted yet does not touch the review.
        $this->quadrant('HH')->conditions()->create(['tenant_id' => $this->project->tenant_id, 'kind' => 'assumption', 'text' => 'Draf AI lain.', 'sort_order' => 9, 'origin' => 'ai', 'review_status' => 'proposed']);
        $this->assertSame('consistent', $this->quadrant('HH')->consistency_status);

        $this->actingAs($this->manager)->put(route('scenario-planning.conditions.update', [$this->project, $pole]), ['kind' => 'pole_expression', 'axis' => 'A', 'text' => 'Regulasi berubah arah.'])->assertSessionHasNoErrors();
        $this->assertNull($this->quadrant('HH')->consistency_status);

        $this->actingAs($this->manager)->get(route('scenario-planning.compare', $this->project))
            ->assertInertia(fn ($page) => $page->where('quadrants.0.consistency.needs_review', true)->where('canManage', true));
    }
}
