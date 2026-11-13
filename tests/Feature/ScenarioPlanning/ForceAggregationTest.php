<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\CriticalUncertainty;
use App\Models\CrossImpact;
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
 * Roadmap R1.3: signals contribute to forces instead of becoming them. Creating
 * a force from a signal needs a force-level name (not the truncated signal
 * title, unless confirmed) and may attach more accepted signals. Merging a
 * duplicate force moves its signal links, cross-impacts, exercise links and
 * scenario axes to the surviving force, keeps a tombstone, and flags affected
 * scenarios as stale. See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 2.
 */
class ForceAggregationTest extends TestCase
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

        foreach (['view-scenario', 'manage-scenario', 'view-driving-force', 'edit-driving-force', 'review-signal', 'view-signal'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'BOD'])->givePermissionTo('view-scenario');
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['view-scenario', 'manage-scenario', 'view-driving-force', 'edit-driving-force', 'review-signal', 'view-signal']);
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

    private function signal(string $title, string $status = 'PENDING', ?string $keyword = null): Signal
    {
        $source = Source::create(['uuid' => (string) Uuid::uuid4(), 'title' => 'Laporan', 'raw_content' => "{$title} dibahas di laporan ini.", 'content_hash' => hash('sha256', (string) Uuid::uuid4()), 'created_by' => $this->manager->id]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'title' => $title, 'summary' => "Ringkasan {$title}.",
            'evidence_quote' => "{$title} dibahas di laporan ini.", 'review_status' => $status, 'quote_verified' => true,
            'dimension_id' => Dimension::where('code', 'TECHNOLOGY')->value('id'),
        ]);
        if ($keyword) {
            $signal->driving_forces()->attach($this->forces[$keyword]->id);
        }

        return $signal;
    }

    private function acceptCreate(Signal $signal, array $data = [])
    {
        return $this->actingAs($this->manager)->postJson("/signals/{$signal->uuid}/review", $data + ['action' => 'accept_create']);
    }

    private function merge(DrivingForce $source, DrivingForce $target)
    {
        return $this->actingAs($this->manager)->postJson(route('driving-force.merge', $source->uuid), ['target' => $target->uuid]);
    }

    private function libraryForce(string $keyword): DrivingForce
    {
        return DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', 'CONSUMER')->value('id'), 'created_by' => $this->manager->id,
            'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
        ]);
    }

    public function test_creating_a_force_from_a_signal_needs_a_force_level_name(): void
    {
        $signal = $this->signal('Baterai solid-state masuk produksi massal');
        $other = $this->signal('Pabrik sel LFP baru dibuka', 'ACCEPTED');

        $this->acceptCreate($signal)->assertStatus(422)->assertJsonValidationErrors('force_keyword');
        $this->acceptCreate($signal, ['force_keyword' => 'Baterai solid-state masuk produksi', 'force_description' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('force_keyword');
        $this->assertSame('PENDING', $signal->fresh()->review_status);

        $this->acceptCreate($signal, [
            'force_keyword' => 'Battery Technology Shift', 'force_description' => 'Pergeseran teknologi baterai kendaraan listrik.',
            'attach_signals' => [$other->uuid],
        ])->assertOk();

        $force = DrivingForce::where('keyword', 'Battery Technology Shift')->sole();
        $this->assertSame('Pergeseran teknologi baterai kendaraan listrik.', $force->description);
        $this->assertSame('Baterai solid-state masuk produksi massal', $signal->fresh()->title);
        $this->assertEqualsCanonicalizing([$signal->id, $other->id], $force->signals()->pluck('signals.id')->all());
    }

    public function test_the_signal_title_can_be_the_force_name_when_confirmed(): void
    {
        $signal = $this->signal('Harga nikel turun tajam');

        $this->acceptCreate($signal, ['force_keyword' => 'Harga nikel turun tajam', 'force_description' => 'Harga nikel.', 'confirm_signal_title' => true])->assertOk();
        $this->assertSame(1, DrivingForce::where('keyword', 'Harga nikel turun tajam')->count());
    }

    public function test_merging_moves_signal_links_cross_impacts_and_scenario_axes_and_keeps_a_tombstone(): void
    {
        $duplicate = $this->libraryForce('Daya Beli Konsumen');
        $evidence = $this->signal('Daya beli melemah', 'ACCEPTED');
        $evidence->driving_forces()->attach($duplicate->id);
        $shared = $this->signal('Konsumsi rumah tangga turun', 'ACCEPTED', 'Purchasing Power');
        $shared->driving_forces()->attach($duplicate->id);
        $evidence->update(['created_driving_force_id' => $duplicate->id]);
        CrossImpact::create(['source_driving_force_id' => $duplicate->id, 'target_driving_force_id' => $this->forces['Technology']->id, 'score' => 2]);
        CrossImpact::create(['source_driving_force_id' => $this->forces['Politic']->id, 'target_driving_force_id' => $duplicate->id, 'score' => -1]);
        CrossImpact::create(['source_driving_force_id' => $this->forces['Politic']->id, 'target_driving_force_id' => $this->forces['Purchasing Power']->id, 'score' => 3]);
        CrossImpact::create(['source_driving_force_id' => $duplicate->id, 'target_driving_force_id' => $this->forces['Purchasing Power']->id, 'score' => 1]);
        $legacy = Scenario::create([
            'name' => 'Lama', 'axis_a_force_id' => $duplicate->id, 'axis_b_force_id' => $this->forces['Technology']->id,
            'axis_a_low_label' => 'Lemah', 'axis_a_high_label' => 'Kuat', 'axis_b_low_label' => 'Lambat', 'axis_b_high_label' => 'Cepat', 'created_by' => $this->manager->id,
        ]);

        $this->merge($duplicate, $this->forces['Purchasing Power'])->assertOk();

        $target = $this->forces['Purchasing Power']->id;
        $tombstone = DrivingForce::withTrashed()->find($duplicate->id);
        $this->assertTrue($tombstone->trashed());
        $this->assertSame($target, $tombstone->merged_into_id);
        $this->assertSame($this->manager->id, $tombstone->merged_by);
        $this->assertEqualsCanonicalizing([$evidence->id, $shared->id], DrivingForce::find($target)->signals()->pluck('signals.id')->all());
        $this->assertSame($target, $evidence->fresh()->created_driving_force_id);
        $this->assertSame(2, (int) CrossImpact::where('source_driving_force_id', $target)->where('target_driving_force_id', $this->forces['Technology']->id)->value('score'));
        // Politic → target existed already, so the duplicate's -1 is dropped; the self-loop disappears.
        $this->assertSame(3, (int) CrossImpact::where('source_driving_force_id', $this->forces['Politic']->id)->where('target_driving_force_id', $target)->value('score'));
        $this->assertSame(0, CrossImpact::where('source_driving_force_id', $target)->where('target_driving_force_id', $target)->count());
        $this->assertSame(0, CrossImpact::where('source_driving_force_id', $duplicate->id)->orWhere('target_driving_force_id', $duplicate->id)->count());
        $this->assertSame($target, $legacy->fresh()->axis_a_force_id);
        $this->assertStringContainsString('digabung', $legacy->fresh()->stale_reason);
    }

    public function test_merging_forces_of_an_exercise_combines_their_links_and_flags_the_framework(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->approveFramework();
        $legislative = $this->link($this->project, 'Legislative');
        $politic = $this->link($this->project, 'Politic');

        $this->merge($this->forces['Legislative'], $this->forces['Politic'])->assertOk();

        $this->assertNull(ProjectDrivingForce::find($legislative->id));
        $regulation = CriticalUncertainty::where('name', 'Government Regulation')->sole();
        $this->assertSame([$politic->id], $regulation->projectForces()->pluck('project_driving_forces.id')->all());
        $this->assertStringContainsString('Legislative', $this->framework()->stale_reason);

        // A force that is in no exercise yet takes over the exercise link of the merged one.
        $spending = $this->libraryForce('Consumer Spending');
        $purchasing = $this->link($this->project, 'Purchasing Power');
        $this->merge($this->forces['Purchasing Power'], $spending)->assertOk();
        $this->assertSame($spending->id, $purchasing->fresh()->driving_force_id);
        $this->assertTrue($purchasing->fresh()->is_predetermined);
    }

    public function test_merge_rules(): void
    {
        $this->merge($this->forces['Politic'], $this->forces['Politic'])->assertStatus(422)->assertJsonValidationErrors('target');

        $gone = $this->libraryForce('Sudah Dihapus');
        $gone->delete();
        $this->merge($this->forces['Politic'], $gone)->assertStatus(422)->assertJsonValidationErrors('target');

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view-driving-force');
        $this->actingAs($viewer)->postJson(route('driving-force.merge', $this->forces['Politic']->uuid), ['target' => $this->forces['Legislative']->uuid])->assertForbidden();
        $this->assertFalse($this->forces['Politic']->fresh()->trashed());
    }
}
