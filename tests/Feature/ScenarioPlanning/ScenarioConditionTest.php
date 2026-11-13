<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\AiGenerationRun;
use App\Models\CriticalUncertainty;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
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
 * Roadmap R1.5: step 5 describes each future before its narrative. Each
 * quadrant of the approved framework gets an ordered list of conditions (pole
 * expression, behaviour of a non-axis force, or assumption); the exercise's
 * predetermined elements hold in all four. Step 5 is done when every quadrant
 * has at least two conditions.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 5.
 */
class ScenarioConditionTest extends TestCase
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

    /** An approved framework on the sample exercise (gate G5). */
    private function readyFramework(): Scenario
    {
        $this->approveAxes();
        $this->createFramework();
        $this->approveFramework()->assertSessionHasNoErrors();

        return $this->framework();
    }

    private function addCondition(string $position, array $data = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant($position)]), $data + [
            'kind' => 'assumption',
            'text' => 'Harga baterai turun 40% sebelum 2028.',
        ]);
    }

    /** @return array<int, string> */
    private function texts(string $position): array
    {
        return $this->quadrant($position)->conditions()->pluck('text')->all();
    }

    public function test_conditions_wait_for_an_approved_framework(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->addCondition('HH')->assertSessionHasErrors('conditions');
        $this->assertSame(0, ScenarioCondition::count());
    }

    public function test_conditions_can_be_added_in_order_with_kind_axis_and_force(): void
    {
        $this->readyFramework();

        $this->addCondition('HH', ['kind' => 'pole_expression', 'axis' => 'A', 'text' => 'Insentif TKDN diperluas.'])->assertSessionHasNoErrors();
        $this->addCondition('HH', ['kind' => 'force_behaviour', 'force' => $this->link($this->project, 'Purchasing Power')->uuid, 'text' => 'Daya beli kelas menengah stabil.'])->assertSessionHasNoErrors();

        $conditions = $this->quadrant('HH')->conditions()->get();
        $this->assertSame(['Insentif TKDN diperluas.', 'Daya beli kelas menengah stabil.'], $conditions->pluck('text')->all());
        $this->assertSame([1, 2], $conditions->pluck('sort_order')->all());
        $this->assertSame(['A', null], $conditions->pluck('axis')->all());
        $this->assertSame($this->link($this->project, 'Purchasing Power')->id, $conditions[1]->project_driving_force_id);
        $this->assertSame(['human', 'accepted'], [$conditions[0]->origin, $conditions[0]->review_status]);
    }

    public function test_validation_follows_the_kind(): void
    {
        $this->readyFramework();

        $this->addCondition('HH', ['kind' => 'pole_expression', 'text' => 'Tanpa sumbu.'])->assertSessionHasErrors('axis');
        $this->addCondition('HH', ['kind' => 'force_behaviour', 'text' => 'Tanpa force.'])->assertSessionHasErrors('force');
        $this->addCondition('HH', ['kind' => 'assumption', 'text' => str_repeat('a', 501)])->assertSessionHasErrors('text');
        // An axis force is described by its pole, not as another force's behaviour.
        $this->addCondition('HH', ['kind' => 'force_behaviour', 'force' => $this->link($this->project, 'Politic')->uuid, 'text' => 'Politik stabil.'])->assertSessionHasErrors('force');
        $this->assertSame(0, ScenarioCondition::count());
    }

    public function test_conditions_can_be_edited_reordered_and_deleted(): void
    {
        $this->readyFramework();
        $this->addCondition('LL', ['text' => 'Pertama.']);
        $this->addCondition('LL', ['text' => 'Kedua.']);
        $this->addCondition('LL', ['text' => 'Ketiga.']);
        [$first, $second, $third] = $this->quadrant('LL')->conditions()->get()->all();

        $this->actingAs($this->manager)->put(route('scenario-planning.conditions.update', [$this->project, $second]), ['kind' => 'assumption', 'text' => 'Kedua, diubah.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.move', [$this->project, $third]), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame(['Pertama.', 'Ketiga.', 'Kedua, diubah.'], $this->texts('LL'));

        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.move', [$this->project, $first]), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame(['Pertama.', 'Ketiga.', 'Kedua, diubah.'], $this->texts('LL'));

        $this->actingAs($this->manager)->delete(route('scenario-planning.conditions.destroy', [$this->project, $first]))->assertSessionHasNoErrors();
        $this->assertSame(['Ketiga.', 'Kedua, diubah.'], $this->texts('LL'));
    }

    public function test_viewers_and_other_exercises_cannot_touch_conditions(): void
    {
        $this->readyFramework();
        $this->addCondition('HH');
        $condition = ScenarioCondition::sole();

        $this->actingAs($this->viewer)->post(route('scenario-planning.conditions.store', [$this->project, $this->quadrant('HH')]), ['kind' => 'assumption', 'text' => 'X'])->assertForbidden();

        $other = $this->exercise();
        $this->actingAs($this->manager)->delete(route('scenario-planning.conditions.destroy', [$other, $condition]))->assertNotFound();
        $this->assertSame(1, ScenarioCondition::count());
    }

    public function test_the_page_lists_predetermined_elements_and_flags_conditions_shared_by_all_quadrants(): void
    {
        $this->readyFramework();
        foreach (Scenario::POSITIONS as $position) {
            $this->addCondition($position, ['text' => 'Infrastruktur listrik PLN tetap memadai.']);
        }
        $this->addCondition('HH', ['text' => 'Hanya di satu kuadran.']);

        $this->actingAs($this->viewer)->get(route('scenario-planning.conditions.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Conditions')
                ->where('predetermined.0.keyword', 'Purchasing Power')
                ->has('quadrants', 4)
                ->where('quadrants.0.position', 'HH')
                ->where('quadrants.0.conditions.0.shared', true)
                ->where('quadrants.0.conditions.1.shared', false)
                ->where('forces.0.keyword', 'Purchasing Power')
                ->has('forces', 1)
                ->where('canManage', false));
    }

    public function test_the_page_renders_while_some_quadrants_have_no_conditions(): void
    {
        $this->readyFramework();
        $this->addCondition('HH');

        $this->actingAs($this->manager)->get(route('scenario-planning.conditions.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('quadrants.0.conditions.0.shared', false)->where('quadrants.1.conditions', []));
    }

    public function test_step_five_is_done_when_every_quadrant_has_two_conditions(): void
    {
        $this->readyFramework();
        $step = fn () => $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project));

        $step()->assertInertia(fn ($page) => $page->where('steps.4.status', 'available'));
        $this->addCondition('HH');
        $step()->assertInertia(fn ($page) => $page->where('steps.4.status', 'in_progress'));

        foreach (Scenario::POSITIONS as $position) {
            $this->addCondition($position, ['text' => "Kondisi A {$position}."]);
            $this->addCondition($position, ['text' => "Kondisi B {$position}."]);
        }
        $step()->assertInertia(fn ($page) => $page->where('steps.4.status', 'done'));
    }

    private function draft(string $position, string $kind, string $text, ?string $axis = null, ?string $force = null): array
    {
        return ['position' => $position, 'kind' => $kind, 'text' => $text, 'axis' => $axis, 'force' => $force];
    }

    /** Roadmap R2.5 (conditions-v1): AI drafts conditions per quadrant as proposals, accepted or discarded one by one. */
    public function test_ai_drafts_conditions_as_proposals_reviewed_one_by_one(): void
    {
        $this->readyFramework();
        $this->addCondition('HH', ['text' => 'Kondisi manual tim.'])->assertSessionHasNoErrors();
        $fake = $this->fakeLlm();
        $fake->respond(['conditions' => [
            $this->draft('HH', 'pole_expression', 'Regulasi mendukung insentif penuh.', 'A'),
            $this->draft('HL', 'assumption', 'Teknologi baterai berjalan lambat.'),
            $this->draft('LH', 'force_behaviour', 'Daya beli kelas menengah turun.', null, 'F1'),
            $this->draft('LL', 'assumption', 'Transisi tertahan hingga 2030.'),
        ]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.suggest', $this->project))->assertSessionHasNoErrors();

        $prompt = $fake->lastRequest()->user;
        $this->assertSame('scenario_conditions', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('Purchasing Power', $prompt);
        $this->assertStringContainsString('Kondisi manual tim.', $prompt);
        $this->assertSame(4, ScenarioCondition::where('origin', 'ai')->where('review_status', 'proposed')->count());
        $this->assertSame('Purchasing Power', ScenarioCondition::where('kind', 'force_behaviour')->sole()->projectForce->drivingForce->keyword);
        $this->assertSame('completed', AiGenerationRun::sole()->status);

        $this->actingAs($this->viewer)->get(route('scenario-planning.conditions.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('quadrants.0.position', 'HH')
                ->where('quadrants.0.conditions.1.review_status', 'proposed')
                ->where('quadrants.0.conditions.1.origin', 'ai')
                ->where('conditionStatus', 'completed')
                ->where('steps.4.status', 'in_progress'));

        $proposal = ScenarioCondition::where('text', 'Regulasi mendukung insentif penuh.')->sole();
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.accept', [$this->project, $proposal]))->assertSessionHasNoErrors();
        $this->assertSame('accepted', $proposal->fresh()->review_status);

        $discard = ScenarioCondition::where('text', 'Transisi tertahan hingga 2030.')->sole();
        $this->actingAs($this->manager)->delete(route('scenario-planning.conditions.destroy', [$this->project, $discard]))->assertSessionHasNoErrors();
        $this->assertNull($discard->fresh());

        // A new draft replaces only the AI proposals still waiting; accepted conditions stay.
        $fake->respond(['conditions' => [$this->draft('HL', 'assumption', 'Draf baru HL.')]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Draf baru HL.'], ScenarioCondition::where('review_status', 'proposed')->pluck('text')->all());
        $this->assertSame(2, ScenarioCondition::where('review_status', 'accepted')->count());
    }

    public function test_ai_condition_drafts_are_checked_and_follow_the_rules(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.suggest', $this->project))->assertSessionHasErrors('ai');
        $this->approveFramework()->assertSessionHasNoErrors();
        $this->actingAs($this->viewer)->post(route('scenario-planning.conditions.suggest', $this->project))->assertForbidden();

        $this->fakeLlm()->respond(['conditions' => [$this->draft('HH', 'pole_expression', 'Tanpa sumbu.')]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, ScenarioCondition::count());
        $this->assertSame('rejected', AiGenerationRun::sole()->status);
        $this->actingAs($this->manager)->get(route('scenario-planning.conditions.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('conditionStatus', 'failed')->where('conditionError', fn ($error) => str_contains((string) $error, 'sumbu')));

        $this->fakeLlm(available: false);
        $this->actingAs($this->manager)->post(route('scenario-planning.conditions.suggest', $this->project))->assertSessionHasErrors('ai');
    }

    /**
     * Roadmap R2.5 (distinctness check): conditions in different quadrants that share most of their
     * words are listed as overlapping; same-quadrant pairs and texts shared by all four quadrants
     * (already flagged as possible predetermined elements) are not.
     */
    public function test_overlapping_conditions_across_quadrants_are_listed(): void
    {
        $this->readyFramework();
        $this->addCondition('HH', ['text' => 'Harga baterai turun tajam sebelum 2028.']);
        $this->addCondition('HL', ['text' => 'Harga baterai turun tajam sebelum tahun 2028!']);
        $this->addCondition('HH', ['text' => 'Harga baterai turun tajam sebelum 2028, kata analis.']);
        $this->addCondition('LH', ['text' => 'Subsidi kendaraan listrik dihentikan pemerintah.']);
        foreach (['HH', 'HL', 'LH', 'LL'] as $position) {
            $this->addCondition($position, ['text' => 'Inflasi tetap rendah.']);
        }

        $this->actingAs($this->viewer)->get(route('scenario-planning.conditions.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->has('overlaps', 2)
                ->where('overlaps.0.a.position', 'HH')
                ->where('overlaps.0.b.position', 'HL')
                ->where('overlaps.0.b.text', 'Harga baterai turun tajam sebelum tahun 2028!')
                ->where('overlaps.0.similarity', fn ($value) => $value >= 0.6 && $value < 1)
                ->where('overlaps.1.a.text', 'Harga baterai turun tajam sebelum 2028, kata analis.')
                ->where('quadrants.0.position', 'HH')
                ->where('quadrants.0.conditions.0.similar_to', ['HL'])
                ->where('quadrants.0.conditions.2.similar_to', [])
                ->where('quadrants.2.conditions.0.similar_to', []));
    }
}
