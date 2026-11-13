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
 * Roadmap R1.6: narratives of an exercise framework use prompt scenario-v2
 * (conditions, predetermined elements, target year, path from today) with
 * evidence from condition-linked forces too, and each quadrant has a review
 * state (draft / reviewed / approved, gate G6). Step 6 is done when all four
 * quadrants are approved.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 6.
 */
class ScenarioNarrativeReviewTest extends TestCase
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

    private function addCondition(string $position, array $data): void
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant($position)]), $data)->assertSessionHasNoErrors();
    }

    private function acceptedSignal(string $keyword, string $title): Signal
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => "Report on {$title}", 'raw_content' => "{$title} is described in this report.",
            'content_hash' => hash('sha256', $title), 'created_by' => $this->manager->id,
        ]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'title' => $title, 'summary' => "Summary of {$title}.",
            'evidence_quote' => "{$title} is described in this report.", 'review_status' => 'ACCEPTED', 'reviewed_at' => now(), 'quote_verified' => true,
        ]);
        $signal->driving_forces()->attach($this->forces[$keyword]->id);

        return $signal;
    }

    /** Four quadrants citing the given reference. */
    private function narrative(string $ref = 'S1'): array
    {
        return ['quadrants' => array_map(fn ($position) => [
            'position' => $position, 'title' => "Masa depan {$position}", 'narrative' => "Narasi {$position} [{$ref}].",
            'implications' => "Implikasi {$position}.", 'early_indicators' => ["Indikator {$position}"], 'cited_refs' => [$ref],
        ], Scenario::POSITIONS)];
    }

    private function generate(array $body = [])
    {
        return $this->actingAs($this->manager)->postJson("/scenarios/{$this->framework()->uuid}/generate", $body);
    }

    private function review(string $position, string $status)
    {
        return $this->actingAs($this->manager)->postJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant($position)->uuid}/review", ['status' => $status]);
    }

    /** An approved framework with an AI narrative in all four quadrants. */
    private function aiNarratives(): void
    {
        $llm = $this->fakeLlm();
        $this->readyFramework();
        $this->acceptedSignal('Politic', 'Insentif TKDN diperluas');
        $this->acceptedSignal('Technology', 'Harga baterai LFP turun');
        $llm->respond($this->narrative());
        $this->generate()->assertStatus(202);
    }

    public function test_the_v2_prompt_carries_conditions_predetermined_elements_and_the_target_year(): void
    {
        $llm = $this->fakeLlm();
        $this->readyFramework();
        $this->addCondition('HH', ['kind' => 'pole_expression', 'axis' => 'A', 'text' => 'Insentif TKDN diperluas ke baterai.']);
        $this->addCondition('LL', ['kind' => 'force_behaviour', 'force' => $this->link($this->project, 'Purchasing Power')->uuid, 'text' => 'Daya beli tertekan inflasi.']);
        $this->acceptedSignal('Politic', 'Aturan TKDN baru');
        $this->acceptedSignal('Technology', 'Baterai solid-state diuji');
        $this->acceptedSignal('Purchasing Power', 'Inflasi menekan daya beli');
        $llm->respond($this->narrative());

        $this->generate()->assertStatus(202);

        $user = $llm->lastRequest()->user;
        $this->assertStringContainsString('Target year: '.$this->project->target_year, $user);
        $this->assertStringContainsString('Today: '.now()->year, $user);
        $this->assertStringContainsString('Predetermined elements (hold in all four quadrants): Purchasing Power', $user);
        $this->assertStringContainsString('HH (Favorable · Fast)', $user);
        $this->assertStringContainsString('[pole A] Insentif TKDN diperluas ke baterai.', $user);
        $this->assertStringContainsString('[Purchasing Power] Daya beli tertekan inflasi.', $user);
        // Signals of a force named by a condition are evidence too.
        $this->assertStringContainsString('S3 (condition force): Inflasi menekan daya beli', $user);
        $this->assertSame('scenario-v3', $this->framework()->prompt_version);
    }

    public function test_generated_quadrants_start_as_drafts_and_approval_records_who(): void
    {
        $this->aiNarratives();

        $this->assertSame(['draft'], $this->framework()->quadrants()->pluck('review_status')->unique()->values()->all());

        $this->review('HH', 'reviewed')->assertOk()->assertJsonPath('scenario.quadrants.0.review_status', 'reviewed');
        $this->review('HH', 'approved')->assertOk();

        $quadrant = $this->quadrant('HH');
        $this->assertSame(['approved', $this->manager->id], [$quadrant->review_status, $quadrant->reviewed_by]);
        $this->assertNotNull($quadrant->reviewed_at);
        $this->actingAs($this->manager)->getJson("/scenarios/{$this->framework()->uuid}")->assertJsonPath('quadrants.0.reviewed_by', $this->manager->name);

        $this->review('HH', 'draft')->assertOk();
        $this->assertSame(['draft', null], [$this->quadrant('HH')->review_status, $this->quadrant('HH')->reviewed_by]);
    }

    public function test_approving_all_four_quadrants_completes_step_six(): void
    {
        $this->aiNarratives();
        $step = fn () => $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project));

        $step()->assertInertia(fn ($page) => $page->where('steps.5.status', 'in_progress')->where('project.unreviewed_narratives', 4));

        foreach (['HH', 'HL', 'LH'] as $position) {
            $this->review($position, 'approved')->assertOk();
        }
        $step()->assertInertia(fn ($page) => $page->where('steps.5.status', 'in_progress')->where('project.unreviewed_narratives', 1));

        $this->review('LL', 'approved')->assertOk();
        $step()->assertInertia(fn ($page) => $page->where('steps.5.status', 'done')->where('project.unreviewed_narratives', 0));
    }

    public function test_an_unedited_template_cannot_be_approved(): void
    {
        $this->readyFramework();
        $this->generate()->assertStatus(202);
        $step = fn () => $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project));
        // PRD-07: a template skeleton nobody edited is not a started narrative.
        $step()->assertInertia(fn ($page) => $page->where('steps.5.status', 'available'));

        $this->review('HH', 'approved')->assertStatus(422)->assertJsonValidationErrors('status');

        $this->actingAs($this->manager)->putJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant('HH')->uuid}", [
            'title' => $this->quadrant('HH')->title, 'narrative' => 'Narasi yang ditulis tim.',
        ])->assertOk();
        $step()->assertInertia(fn ($page) => $page->where('steps.5.status', 'in_progress'));
        $this->review('HH', 'approved')->assertOk();
    }

    public function test_reviewed_quadrants_are_protected_from_regeneration_and_edits_reset_approval(): void
    {
        $this->aiNarratives();
        $this->review('HL', 'approved');
        $llm = $this->fakeLlm();
        $llm->respond($this->narrative());

        $this->generate()->assertStatus(409);
        $this->assertSame('approved', $this->quadrant('HL')->review_status);

        $this->generate(['overwrite_edited' => true])->assertStatus(202);
        $this->assertSame('draft', $this->quadrant('HL')->review_status);

        $this->review('LH', 'approved');
        $this->actingAs($this->manager)->putJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant('LH')->uuid}", [
            'title' => $this->quadrant('LH')->title, 'narrative' => 'Narasi diubah setelah disetujui.',
        ])->assertOk();
        $this->assertSame('draft', $this->quadrant('LH')->review_status);

        // Quadrant names of an approved framework stay locked here too (G5).
        $this->actingAs($this->manager)->putJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant('LH')->uuid}", [
            'title' => 'Nama lain', 'narrative' => 'Narasi.',
        ])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_viewers_cannot_review(): void
    {
        $this->aiNarratives();

        $this->actingAs($this->viewer)->postJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant('HH')->uuid}/review", ['status' => 'approved'])->assertForbidden();
    }

    /* Audit 2026-11-11 U3 (AUD-01..03): Step 6 inside the exercise. */

    private function studio(string $name, array $parameters = []): string
    {
        return route("scenario-planning.narratives.{$name}", [$this->project, ...$parameters]);
    }

    private function editQuadrant(string $position, string $narrative, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->putJson($this->studio('quadrant', [$this->quadrant($position)]), [
            'title' => $this->quadrant($position)->title, 'narrative' => $narrative,
        ]);
    }

    public function test_step_six_opens_the_exercise_narrative_studio(): void
    {
        $this->aiNarratives();

        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page->where('steps.5.href', $this->studio('index'))->where('steps.5.scoped', true));

        $this->actingAs($this->viewer)->get($this->studio('index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Narratives')
                ->where('canManage', false)
                ->where('framework.id', $this->framework()->uuid)
                ->where('framework.framework_status', 'approved')
                ->has('framework.quadrants', 4)
                ->where('steps.5.status', 'in_progress'));
        $this->actingAs($this->manager)->get($this->studio('index'))->assertInertia(fn ($page) => $page->where('canManage', true));
        $this->actingAs($this->manager)->getJson($this->studio('state'))->assertOk()->assertJsonPath('id', $this->framework()->uuid);
    }

    public function test_narratives_are_generated_edited_and_approved_inside_the_exercise(): void
    {
        $llm = $this->fakeLlm();
        $this->readyFramework();
        $this->acceptedSignal('Politic', 'Insentif TKDN diperluas');
        $this->acceptedSignal('Technology', 'Harga baterai LFP turun');
        $llm->respond($this->narrative());

        $this->actingAs($this->manager)->postJson($this->studio('generate'))->assertStatus(202);
        $this->assertSame('ai', $this->quadrant('HH')->origin);

        $this->editQuadrant('HH', 'Narasi HH disunting tim [S1].')->assertOk()->assertJsonPath('scenario.quadrants.0.narrative', 'Narasi HH disunting tim [S1].');
        foreach (Scenario::POSITIONS as $position) {
            $this->actingAs($this->manager)->postJson($this->studio('review', [$this->quadrant($position)]), ['status' => 'approved'])->assertOk();
        }

        $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project))->assertInertia(fn ($page) => $page->where('steps.5.status', 'done'));
        $this->assertSame(4, $this->project->decisions()->where('gate', 'G6')->where('action', 'approved')->count());
    }

    public function test_the_exercise_coordinator_manages_narratives_and_viewers_cannot(): void
    {
        $this->aiNarratives();
        $owner = User::factory()->create();
        $owner->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->project->update(['owner_user_id' => $owner->id]);
        $outsider = User::factory()->create();
        $outsider->givePermissionTo(['view-scenario', 'manage-scenario']);

        // The owner did not create the framework, yet coordinates the exercise.
        $this->editQuadrant('HH', 'Narasi pemilik latihan.', $owner)->assertOk();
        $this->actingAs($owner)->postJson($this->studio('review', [$this->quadrant('HH')]), ['status' => 'reviewed'])->assertOk();
        // The same rule holds on the old page for an exercise framework.
        $this->actingAs($owner)->postJson("/scenarios/{$this->framework()->uuid}/quadrants/{$this->quadrant('HH')->uuid}/review", ['status' => 'draft'])->assertOk();

        $this->editQuadrant('HH', 'Bukan koordinator.', $outsider)->assertForbidden();
        $this->actingAs($outsider)->postJson($this->studio('generate'))->assertForbidden();
        $this->actingAs($this->viewer)->postJson($this->studio('review', [$this->quadrant('HH')]), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->viewer)->postJson($this->studio('generate'))->assertForbidden();
        $this->assertSame('Narasi pemilik latihan.', $this->quadrant('HH')->narrative);
    }

    public function test_a_quadrant_of_another_framework_is_not_found(): void
    {
        $this->aiNarratives();
        $other = $this->framework()->replicate(['uuid']);
        $other->forceFill(['uuid' => (string) Uuid::uuid4(), 'project_id' => null])->save();
        $foreign = $this->quadrant('HH')->replicate(['uuid']);
        $foreign->forceFill(['uuid' => (string) Uuid::uuid4(), 'scenario_id' => $other->id])->save();

        $this->actingAs($this->manager)->putJson($this->studio('quadrant', [$foreign]), ['title' => $foreign->title, 'narrative' => 'x'])->assertNotFound();
        $this->actingAs($this->manager)->postJson($this->studio('review', [$foreign]), ['status' => 'reviewed'])->assertNotFound();
    }

    public function test_the_studio_needs_an_approved_framework(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->actingAs($this->manager)->get($this->studio('index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('framework.framework_status', 'draft'));
        $this->actingAs($this->manager)->postJson($this->studio('generate'))->assertStatus(422);
    }

    public function test_revisions_keep_the_text_an_edit_or_regeneration_replaces(): void
    {
        $this->aiNarratives();
        $original = $this->quadrant('HH')->narrative;
        $cited = $this->quadrant('HH')->cited_signal_ids;

        $this->editQuadrant('HH', 'Narasi HH versi tim.')->assertOk();
        // Saving the same text again keeps no copy.
        $this->editQuadrant('HH', 'Narasi HH versi tim.')->assertOk();

        $revisions = $this->quadrant('HH')->revisions()->get();
        $this->assertCount(1, $revisions);
        $this->assertSame([$original, 'edit', 'ai', $this->manager->id], [$revisions[0]->narrative, $revisions[0]->reason, $revisions[0]->origin, $revisions[0]->user_id]);
        $this->assertSame($cited, $revisions[0]->cited_signal_ids);

        $llm = $this->fakeLlm();
        $llm->respond($this->narrative());
        $this->actingAs($this->manager)->postJson($this->studio('generate'), ['overwrite_edited' => true])->assertStatus(202);

        $this->assertSame(['edit', 'regenerate'], $this->quadrant('HH')->revisions()->oldest('id')->pluck('reason')->all());
        $this->assertSame('Narasi HH versi tim.', $this->quadrant('HH')->revisions()->latest('id')->value('narrative'));
        $this->assertSame(1, $this->quadrant('LL')->revisions()->count());

        $this->actingAs($this->viewer)->get($this->studio('index'))
            ->assertInertia(fn ($page) => $page->has('revisions.'.$this->quadrant('HH')->uuid, 2)
                ->where('revisions.'.$this->quadrant('HH')->uuid.'.0.reason', 'regenerate')
                ->where('revisions.'.$this->quadrant('HH')->uuid.'.0.narrative', 'Narasi HH versi tim.')
                ->where('revisions.'.$this->quadrant('HH')->uuid.'.0.user', $this->manager->name));
    }
}
