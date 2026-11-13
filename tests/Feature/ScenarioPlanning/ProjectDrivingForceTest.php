<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\AiGenerationRun;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\ForceCandidate;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R0.2: each exercise has its own driving force list, picked from the
 * organization's library, with per-exercise inclusion, influence note and
 * predetermined flag, and gate G2 (force list agreed) before step 3.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 2.
 */
class ProjectDrivingForceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private Dimension $legislative;

    private Dimension $technology;

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
        $this->technology = Dimension::where('code', 'TECHNOLOGY')->sole();
    }

    private function project(bool $confirmed = true, string $name = 'Elektrifikasi 2030'): ScenarioPlanningProject
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => $name,
            'focal_question' => 'How Indonesia vehicle electrification look like in 2030?',
            'purpose' => 'Strategic proposal toward 2030.',
            'target_year' => now()->year + 4,
        ])->assertSessionHasNoErrors();
        $project = ScenarioPlanningProject::latest('id')->firstOrFail();
        if ($confirmed) {
            $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project));
        }

        return $project->fresh();
    }

    private function force(string $keyword, ?Dimension $dimension = null, array $attributes = []): DrivingForce
    {
        return DrivingForce::create($attributes + [
            'uuid' => (string) Uuid::uuid4(),
            'dimension_id' => ($dimension ?? $this->legislative)->id,
            'created_by' => $this->manager->id,
            'keyword' => $keyword,
            'description' => "{$keyword} description.",
            'status' => 'PENDING',
        ]);
    }

    private function add(ScenarioPlanningProject $project, array $forces)
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $project), [
            'forces' => array_map(fn (DrivingForce $force) => $force->uuid, $forces),
        ]);
    }

    private function link(ScenarioPlanningProject $project, DrivingForce $force): ProjectDrivingForce
    {
        return ProjectDrivingForce::where('project_id', $project->id)->where('driving_force_id', $force->id)->sole();
    }

    /** PI-06: rejected and closed firm forces are not offered to an exercise and cannot be added. */
    public function test_rejected_and_closed_forces_stay_out_of_an_exercise(): void
    {
        $project = $this->project();
        $pending = $this->force('TKDN Regulation');
        $approved = $this->force('Import Regulation', null, ['status' => 'APPROVED']);
        $rejected = $this->force('Euro-5 Plan', null, ['status' => 'REJECTED']);
        $closed = $this->force('Solid Battery', $this->technology, ['status' => 'CLOSED']);

        $this->actingAs($this->manager)->get(route('scenario-planning.forces.index', $project))
            ->assertInertia(fn ($page) => $page->where('library', fn ($library) => collect($library)->pluck('keyword')->sort()->values()->all() === ['Import Regulation', 'TKDN Regulation']));

        $this->add($project, [$pending, $approved, $rejected, $closed])->assertSessionHasErrors('forces');
        $this->assertSame(0, ProjectDrivingForce::where('project_id', $project->id)->count());
        $this->add($project, [$pending, $approved])->assertSessionHasNoErrors();
        $this->assertSame(2, ProjectDrivingForce::where('project_id', $project->id)->count());
    }

    /** PI-06: a firm force that changes while an exercise uses it flags that exercise link with a reason. */
    public function test_a_firm_force_change_flags_the_exercises_that_use_it(): void
    {
        $project = $this->project();
        $edited = $this->force('TKDN Regulation');
        $rejected = $this->force('Import Regulation', null, ['status' => 'APPROVED']);
        $deleted = $this->force('Solid Battery', $this->technology);
        $untouched = $this->force('Euro-5 Plan');
        $this->add($project, [$edited, $rejected, $deleted, $untouched])->assertSessionHasNoErrors();

        $edited->update(['description' => 'Aturan TKDN baru.']);
        $rejected->update(['status' => 'REJECTED']);
        $deleted->delete();
        $untouched->update(['remark' => 'Catatan internal.']);

        $this->assertStringContainsString('TKDN Regulation diubah di library', (string) $this->link($project, $edited)->stale_reason);
        $this->assertStringContainsString('Import Regulation ditolak di library', (string) $this->link($project, $rejected)->stale_reason);
        $this->assertStringContainsString('Solid Battery dihapus dari library', (string) ProjectDrivingForce::where('driving_force_id', $deleted->id)->sole()->stale_reason);
        $this->assertNull($this->link($project, $untouched)->stale_since);

        $this->actingAs($this->viewer)->get(route('scenario-planning.forces.index', $project))
            ->assertInertia(fn ($page) => $page->where('forces', fn ($forces) => collect($forces)->whereNotNull('stale_reason')->count() === 3));

        // Saving the link in the exercise is the team's review: the flag clears.
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $edited)]), ['influence_note' => 'Masih relevan.'])->assertSessionHasNoErrors();
        $this->assertNull($this->link($project, $edited)->stale_since);
    }

    public function test_forces_from_any_month_can_be_added_and_are_listed_without_a_date_filter(): void
    {
        $project = $this->project();
        $old = $this->force('TKDN Regulation');
        $old->forceFill(['created_at' => now()->subMonths(7)])->save();
        $new = $this->force('Solid Battery', $this->technology);

        $this->add($project, [$old, $new])->assertSessionHasNoErrors();
        $this->add($project, [$old])->assertSessionHasNoErrors();

        $this->assertSame(2, ProjectDrivingForce::where('project_id', $project->id)->count());
        $this->actingAs($this->viewer)->get(route('scenario-planning.forces.index', $project))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/DrivingForces')
                ->has('forces', 2)
                ->where('forces.0.inclusion_status', 'included')
                // Roadmap R0.6: every exercise artefact says where it came from.
                ->where('forces.0.origin', 'library')
                ->has('library', 0));
        $this->assertSame(['library'], ProjectDrivingForce::where('project_id', $project->id)->pluck('origin')->unique()->values()->all());
    }

    public function test_another_tenants_or_deleted_forces_cannot_be_added(): void
    {
        $project = $this->project();
        $deleted = $this->force('Retired force');
        $deleted->delete();
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        $context = app(TenantContext::class);
        $context->setTenant($other);
        $foreign = $this->force('Foreign force');
        $context->reset();

        $this->add($project, [$deleted])->assertSessionHasErrors('forces.0');
        $this->add($project, [$foreign])->assertSessionHasErrors('forces.0');
        $this->assertSame(0, ProjectDrivingForce::count());
    }

    public function test_the_same_force_can_be_included_in_one_project_and_excluded_in_another(): void
    {
        $a = $this->project(name: 'Proyek A');
        $b = $this->project(name: 'Proyek B');
        $force = $this->force('TKDN Regulation');
        $this->add($a, [$force]);
        $this->add($b, [$force]);

        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$a, $this->link($a, $force)]), [
            'inclusion_status' => 'included', 'influence_note' => 'Menentukan insentif lokal.', 'is_predetermined' => false,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$b, $this->link($b, $force)]), [
            'inclusion_status' => 'excluded', 'influence_note' => 'Tidak relevan untuk rantai pasok.', 'is_predetermined' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('included', $this->link($a, $force)->inclusion_status);
        $this->assertSame('Menentukan insentif lokal.', $this->link($a, $force)->influence_note);
        $this->assertSame('excluded', $this->link($b, $force)->inclusion_status);
        $this->assertTrue($this->link($b, $force)->is_predetermined);
    }

    public function test_forces_cannot_be_added_before_the_focal_issue_is_confirmed(): void
    {
        $project = $this->project(confirmed: false);

        $this->add($project, [$this->force('TKDN Regulation')])->assertSessionHasErrors('forces');

        $this->assertSame(0, ProjectDrivingForce::count());
    }

    public function test_viewers_and_non_owners_cannot_change_the_list(): void
    {
        $project = $this->project();
        $force = $this->force('TKDN Regulation');
        $this->add($project, [$force]);
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);
        $link = $this->link($project, $force);

        $this->actingAs($this->viewer)->put(route('scenario-planning.forces.update', [$project, $link]), ['inclusion_status' => 'excluded'])->assertForbidden();
        $this->actingAs($colleague)->put(route('scenario-planning.forces.update', [$project, $link]), ['inclusion_status' => 'excluded'])->assertForbidden();
        $this->actingAs($colleague)->delete(route('scenario-planning.forces.destroy', [$project, $link]))->assertForbidden();
        $this->assertSame('included', $link->fresh()->inclusion_status);
    }

    public function test_a_link_of_another_project_is_not_found(): void
    {
        $a = $this->project(name: 'Proyek A');
        $b = $this->project(name: 'Proyek B');
        $force = $this->force('TKDN Regulation');
        $this->add($a, [$force]);

        $this->actingAs($this->manager)
            ->put(route('scenario-planning.forces.update', [$b, $this->link($a, $force)]), ['inclusion_status' => 'excluded'])
            ->assertNotFound();
    }

    public function test_agreeing_the_list_needs_an_included_force_and_opens_step_three(): void
    {
        $project = $this->project();
        $force = $this->force('TKDN Regulation');

        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project))->assertSessionHasErrors('forces');

        $this->add($project, [$force]);
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.1.status', 'in_progress')->where('steps.2.status', 'locked'));

        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project))->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame($this->manager->id, $project->forces_confirmed_by);
        $this->assertNotNull($project->forces_confirmed_at);
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.1.status', 'done')->where('steps.2.status', 'available'));
    }

    public function test_changing_inclusion_after_agreement_withdraws_it_but_editing_a_note_does_not(): void
    {
        $project = $this->project();
        $first = $this->force('TKDN Regulation');
        $second = $this->force('Solid Battery', $this->technology);
        $this->add($project, [$first, $second]);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));

        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $first)]), [
            'inclusion_status' => 'included', 'influence_note' => 'Catatan baru.', 'is_predetermined' => false,
        ]);
        $this->assertNotNull($project->fresh()->forces_confirmed_at);

        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $second)]), [
            'inclusion_status' => 'excluded',
        ])->assertSessionHas('success');
        $this->assertNull($project->fresh()->forces_confirmed_at);

        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        $this->actingAs($this->manager)->delete(route('scenario-planning.forces.destroy', [$project, $this->link($project, $first)]));
        $this->assertNull($project->fresh()->forces_confirmed_at);
    }

    public function test_withdrawing_the_focal_issue_confirmation_also_withdraws_the_force_list_agreement(): void
    {
        $project = $this->project();
        $this->add($project, [$this->force('TKDN Regulation')]);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));

        $this->actingAs($this->manager)->put(route('scenario-planning.update', $project), [
            'name' => $project->name, 'focal_question' => 'A different focal question for 2035?', 'purpose' => $project->purpose, 'target_year' => now()->year + 9,
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertNull($project->confirmed_at);
        $this->assertNull($project->forces_confirmed_at);
    }

    public function test_category_coverage_lists_the_categories_without_an_included_force(): void
    {
        $project = $this->project();
        $this->add($project, [$this->force('TKDN Regulation'), $this->force('Solid Battery', $this->technology)]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.forces.index', $project))
            ->assertInertia(fn ($page) => $page
                ->has('coverage', 11)
                ->where('coverage.5.name', 'Legislative')
                ->where('coverage.5.included', 1)
                ->where('coverage.0.name', 'Political')
                ->where('coverage.0.included', 0));
    }

    private function signal(string $title, string $status = 'ACCEPTED', ?DrivingForce $force = null): Signal
    {
        $raw = "{$title} raw";
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => "{$title} source", 'url' => 'https://example.test/'.md5($title),
            'raw_content' => $raw, 'content_hash' => hash('sha256', $raw), 'created_by' => $this->manager->id,
        ]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'dimension_id' => $this->technology->id,
            'title' => $title, 'summary' => "{$title}.", 'evidence_quote' => "{$title}.", 'confidence_score' => 0.9, 'review_status' => $status,
        ]);
        $force?->supporting_signals()->attach($signal->id);

        return $signal;
    }

    private function candidate(string $keyword, array $refs, string $dimension = 'TECHNOLOGY'): array
    {
        return ['keyword' => $keyword, 'description' => "{$keyword} grows across Indonesia.", 'dimension' => $dimension, 'signal_refs' => $refs, 'rationale' => 'Beberapa sinyal menunjuk ke arah yang sama.'];
    }

    /** Roadmap R2.4 (candidate forces): AI groups accepted signals not yet behind an exercise force into candidate forces, reviewed one by one. */
    public function test_ai_proposes_candidate_forces_and_accepting_one_adds_it_to_library_and_exercise(): void
    {
        $project = $this->project();
        $existing = $this->force('TKDN Regulation');
        $this->add($project, [$existing]);
        $this->signal('Insentif TKDN diperluas', force: $existing);
        $swap = $this->signal('Battery swap stations open in Jakarta');
        $fleet = $this->signal('Ride-hailing fleets switch to swappable scooters');
        $this->signal('Unreviewed rumour', 'PENDING');
        $fake = $this->fakeLlm();
        $fake->respond(['candidates' => [
            $this->candidate('Battery Swap Network', ['S1', 'S2']),
            $this->candidate('Fleet Electrification', ['S2']),
        ]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.forces.suggest', $project))->assertSessionHasNoErrors();

        $prompt = $fake->lastRequest()->user;
        $this->assertSame('force_cluster', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('Battery swap stations open in Jakarta', $prompt);
        $this->assertStringNotContainsString('Insentif TKDN diperluas', $prompt);
        $this->assertStringNotContainsString('Unreviewed rumour', $prompt);
        $this->assertSame(2, ForceCandidate::where('status', 'proposed')->count());
        $this->assertSame('completed', AiGenerationRun::sole()->status);

        $this->actingAs($this->viewer)->get(route('scenario-planning.forces.index', $project))
            ->assertInertia(fn ($page) => $page
                ->has('candidates', 2)
                ->where('candidates.0.keyword', 'Battery Swap Network')
                ->where('candidates.0.dimension', $this->technology->name)
                ->has('candidates.0.signals', 2)
                ->where('candidateStatus', 'completed'));

        $battery = ForceCandidate::where('keyword', 'Battery Swap Network')->sole();
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.candidates.accept', [$project, $battery]))->assertSessionHasNoErrors();

        $force = DrivingForce::where('keyword', 'Battery Swap Network')->sole();
        $this->assertSame(['PENDING', $this->technology->id, $this->manager->id], [$force->status, $force->dimension_id, $force->created_by]);
        $this->assertEqualsCanonicalizing([$swap->id, $fleet->id], $force->supporting_signals()->pluck('signals.id')->all());
        $this->assertSame('included', $this->link($project, $force)->inclusion_status);
        $this->assertSame(['accepted', $force->id], [$battery->fresh()->status, $battery->fresh()->driving_force_id]);

        $fleetCandidate = ForceCandidate::where('keyword', 'Fleet Electrification')->sole();
        $this->actingAs($this->manager)->delete(route('scenario-planning.forces.candidates.reject', [$project, $fleetCandidate]))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $fleetCandidate->fresh()->status);
        $this->assertSame(0, DrivingForce::where('keyword', 'Fleet Electrification')->count());
        $this->actingAs($this->viewer)->get(route('scenario-planning.forces.index', $project))->assertInertia(fn ($page) => $page->has('candidates', 0));
    }

    public function test_candidate_forces_with_unknown_refs_or_long_keywords_are_rejected_and_rules_apply(): void
    {
        $draft = $this->project(confirmed: false);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.suggest', $draft))->assertSessionHasErrors('ai');

        $project = $this->project(name: 'Lain');
        $this->fakeLlm();
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.suggest', $project))->assertSessionHasErrors('ai');

        $this->signal('Battery swap stations open in Jakarta');
        $this->actingAs($this->viewer)->post(route('scenario-planning.forces.suggest', $project))->assertForbidden();

        $this->fakeLlm()->respond(['candidates' => [$this->candidate('Battery Swap Network', ['S7'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.suggest', $project))->assertSessionHasNoErrors();
        $this->assertSame(0, ForceCandidate::count());
        $this->assertSame('rejected', AiGenerationRun::latest('id')->first()->status);
        $this->actingAs($this->manager)->get(route('scenario-planning.forces.index', $project))
            ->assertInertia(fn ($page) => $page->where('candidateStatus', 'failed')->where('candidateError', fn ($error) => str_contains((string) $error, 'S7')));

        $this->fakeLlm()->respond(['candidates' => [$this->candidate('A Very Long Candidate Keyword', ['S1'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.suggest', $project))->assertSessionHasNoErrors();
        $this->assertSame(0, ForceCandidate::count());
    }
}
