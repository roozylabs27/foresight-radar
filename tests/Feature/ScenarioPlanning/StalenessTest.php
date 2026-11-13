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
use App\Models\Signal;
use App\Models\Source;
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
 * Roadmap R1.10: upstream changes flag the framework and its quadrants as
 * stale with a reason; nothing is deleted or regenerated. "Keep" records who
 * acknowledged and clears the flags; regenerating or editing a narrative
 * clears its quadrant. See docs/product/SCENARIO_PLANNING_TARGET_WORKFLOW.md §6.
 */
class StalenessTest extends TestCase
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

    /** Template narratives in all four quadrants (no LLM key in tests). */
    private function narratives(): void
    {
        $this->actingAs($this->manager)->postJson("/scenarios/{$this->framework()->uuid}/generate")->assertStatus(202);
    }

    private function score(string $keyword, int $impact): void
    {
        $this->actingAs($this->manager)->put(route('scenario-planning.assessment.update', [$this->project, $this->link($this->project, $keyword)]), [
            'impact' => $impact, 'uncertainty' => 5, 'impact_rationale' => 'Diperbarui.', 'uncertainty_rationale' => 'Diperbarui.',
        ])->assertSessionHasNoErrors();
    }

    private function acceptedSignal(string $keyword): Signal
    {
        $source = Source::create(['uuid' => (string) Uuid::uuid4(), 'title' => 'Laporan', 'raw_content' => 'Isi laporan.', 'content_hash' => hash('sha256', (string) Uuid::uuid4()), 'created_by' => $this->manager->id]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'title' => 'Sinyal TKDN', 'summary' => 'Ringkasan.',
            'evidence_quote' => 'Isi laporan.', 'review_status' => 'ACCEPTED', 'reviewed_at' => now(), 'quote_verified' => true,
        ]);
        $signal->driving_forces()->attach($this->forces[$keyword]->id);

        return $signal;
    }

    public function test_a_score_change_on_an_axis_force_flags_the_framework_and_its_quadrants(): void
    {
        $this->readyFramework();

        $this->score('Purchasing Power', 3);
        $this->assertNull($this->framework()->stale_since);

        $this->score('Politic', 4);
        $framework = $this->framework();
        $this->assertNotNull($framework->stale_since);
        $this->assertStringContainsString('Politic', $framework->stale_reason);
        $this->assertSame(4, $framework->quadrants()->whereNotNull('stale_since')->count());
    }

    public function test_changing_the_focal_issue_flags_the_framework(): void
    {
        $this->readyFramework();

        $this->actingAs($this->manager)->put(route('scenario-planning.update', $this->project), [
            'name' => 'Elektrifikasi 2030', 'focal_question' => 'Bagaimana pasar EV roda dua pada 2030?',
            'purpose' => 'Strategic proposal toward 2030.', 'target_year' => now()->year + 4,
        ])->assertSessionHasNoErrors();

        $this->assertStringContainsString('Focal issue', $this->framework()->stale_reason);
    }

    public function test_changing_conditions_after_a_narrative_flags_only_that_quadrant(): void
    {
        $this->readyFramework();
        $add = fn (string $text) => $this->actingAs($this->manager)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant('HH')]), ['kind' => 'assumption', 'text' => $text]);

        $add('Sebelum narasi.')->assertSessionHasNoErrors();
        $this->assertSame(0, ScenarioQuadrant::whereNotNull('stale_since')->count());

        $this->narratives();
        $add('Setelah narasi.')->assertSessionHasNoErrors();

        $this->assertNotNull($this->quadrant('HH')->stale_since);
        $this->assertStringContainsString('Kondisi', $this->quadrant('HH')->stale_reason);
        $this->assertNull($this->quadrant('LL')->stale_since);
    }

    public function test_changing_poles_after_narratives_flags_the_quadrants(): void
    {
        $this->readyFramework();
        $this->narratives();
        $this->actingAs($this->manager)->post(route('scenario-planning.framework.reopen', $this->project));

        $this->actingAs($this->manager)->put(route('scenario-planning.framework.update', $this->project), [
            'axis_a_low_label' => 'Restrictive', 'axis_a_high_label' => 'Supportive', 'axis_b_low_label' => 'Slow', 'axis_b_high_label' => 'Fast',
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $this->framework()->quadrants()->whereNotNull('stale_since')->count());
        $this->assertStringContainsString('Kutub', $this->quadrant('HH')->stale_reason);
    }

    public function test_rejecting_or_deleting_a_cited_signal_flags_the_quadrants_that_cite_it(): void
    {
        $this->readyFramework();
        $rejected = $this->acceptedSignal('Politic');
        $deleted = $this->acceptedSignal('Technology');
        $this->narratives();
        $this->assertContains($rejected->id, $this->quadrant('HH')->cited_signal_ids);

        $rejected->update(['review_status' => 'REJECTED']);
        $this->assertStringContainsString('Sinyal', $this->quadrant('HH')->stale_reason);

        $this->quadrant('HH')->update(['stale_since' => null, 'stale_reason' => null]);
        $deleted->delete();
        $this->assertNotNull($this->quadrant('HH')->stale_since);
    }

    public function test_keep_records_who_acknowledged_and_clears_the_flags(): void
    {
        $this->readyFramework();
        $this->score('Politic', 4);

        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('project.stale.reasons.0', fn ($reason) => str_contains($reason, 'Politic')));

        $this->actingAs($this->viewer)->post(route('scenario-planning.stale.acknowledge', $this->project))->assertForbidden();
        $this->actingAs($this->manager)->post(route('scenario-planning.stale.acknowledge', $this->project))->assertSessionHasNoErrors();

        $framework = $this->framework();
        $this->assertNull($framework->stale_since);
        $this->assertSame($this->manager->id, $framework->stale_acknowledged_by);
        $this->assertNotNull($framework->stale_acknowledged_at);
        $this->assertSame(0, $framework->quadrants()->whereNotNull('stale_since')->count());
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('project.stale', null));
    }

    public function test_regenerating_or_editing_a_narrative_clears_its_quadrant_flag(): void
    {
        $this->readyFramework();
        $this->narratives();
        $this->framework()->quadrants()->update(['stale_since' => now(), 'stale_reason' => 'Uji.']);

        $this->actingAs($this->manager)->putJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant('HH')->uuid}", [
            'title' => $this->quadrant('HH')->title, 'narrative' => 'Narasi ditulis ulang.',
        ])->assertOk();
        $this->assertNull($this->quadrant('HH')->stale_since);
        $this->assertNotNull($this->quadrant('LL')->stale_since);

        $this->actingAs($this->manager)->postJson("/scenarios/{$this->framework()->uuid}/generate", ['overwrite_edited' => true])->assertStatus(202);
        $this->assertSame(0, $this->framework()->quadrants()->whereNotNull('stale_since')->count());
    }
}
