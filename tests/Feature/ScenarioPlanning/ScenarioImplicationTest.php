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
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\ScenarioImplicationService;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\KeepsLibraryScores;
use Tests\TestCase;

/**
 * Roadmap R1.7: step 7 lists the opportunities and challenges of each future
 * as items (impact level, priority) linked to exercise forces and to the
 * quadrant's conditions. Old free-text implications become one untyped
 * proposal per quadrant. Step 7 is done when every quadrant has at least one
 * opportunity and one challenge.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 7.
 */
class ScenarioImplicationTest extends TestCase
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

    public function test_items_wait_for_an_approved_framework(): void
    {
        $this->approveAxes();
        $this->createFramework();

        $this->addItem('HH')->assertSessionHasErrors('implications');
        $this->assertSame(0, ScenarioImplication::count());
    }

    public function test_the_deck_example_can_be_entered_with_links_to_forces_and_conditions(): void
    {
        $this->readyFramework();
        $condition = $this->condition('HH', 'Insentif fiskal untuk EV diperpanjang.');

        $this->addItem('HH', [
            'type' => 'opportunity', 'title' => 'Pasar EV domestik tumbuh cepat', 'description' => 'Insentif dan TKDN mendorong permintaan.',
            'impact_level' => 'high', 'priority' => 1,
            'forces' => [$this->link($this->project, 'Politic')->uuid], 'conditions' => [$condition->uuid],
        ])->assertSessionHasNoErrors();
        $this->addItem('HH', ['type' => 'challenge', 'title' => 'Kapasitas produksi baterai lokal belum siap', 'impact_level' => 'high', 'priority' => 1])->assertSessionHasNoErrors();
        $this->addItem('HH', ['type' => 'challenge', 'title' => 'Infrastruktur pengisian daya tertinggal', 'impact_level' => 'medium', 'priority' => 2])->assertSessionHasNoErrors();

        $items = $this->quadrant('HH')->implicationItems()->get();
        $this->assertSame(['opportunity', 'challenge', 'challenge'], $items->pluck('type')->all());
        $opportunity = $items->firstWhere('type', 'opportunity');
        $this->assertSame(['high', 1, 'human', 'accepted'], [$opportunity->impact_level, $opportunity->priority, $opportunity->origin, $opportunity->review_status]);
        $this->assertSame([$this->link($this->project, 'Politic')->id], $opportunity->projectForces()->pluck('project_driving_forces.id')->all());
        $this->assertSame([$condition->id], $opportunity->conditions()->pluck('scenario_conditions.id')->all());
    }

    public function test_validation_rules(): void
    {
        $this->readyFramework();
        $otherQuadrantCondition = $this->condition('LL', 'Kondisi kuadran lain.');

        $this->addItem('HH', ['type' => 'risk'])->assertSessionHasErrors('type');
        $this->addItem('HH', ['title' => str_repeat('a', 161)])->assertSessionHasErrors('title');
        $this->addItem('HH', ['impact_level' => 'extreme'])->assertSessionHasErrors('impact_level');
        $this->addItem('HH', ['conditions' => [$otherQuadrantCondition->uuid]])->assertSessionHasErrors('conditions');
        $other = $this->exercise();
        $foreign = ProjectDrivingForce::where('project_id', $other->id)->firstOrFail();
        $this->addItem('HH', ['forces' => [$foreign->uuid]])->assertSessionHasErrors('forces');
        $this->assertSame(0, ScenarioImplication::count());
    }

    public function test_items_can_be_edited_and_deleted_and_stay_in_their_exercise(): void
    {
        $this->readyFramework();
        $this->addItem('LH');
        $item = ScenarioImplication::sole();

        $this->actingAs($this->manager)->put(route('scenario-planning.implications.update', [$this->project, $item]), ['type' => 'challenge', 'title' => 'Diubah', 'impact_level' => 'low'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['challenge', 'Diubah', 'low'], [$item->fresh()->type, $item->fresh()->title, $item->fresh()->impact_level]);

        $other = $this->exercise();
        $this->actingAs($this->manager)->delete(route('scenario-planning.implications.destroy', [$other, $item]))->assertNotFound();
        $this->actingAs($this->viewer)->delete(route('scenario-planning.implications.destroy', [$this->project, $item]))->assertForbidden();

        $this->actingAs($this->manager)->delete(route('scenario-planning.implications.destroy', [$this->project, $item]))->assertSessionHasNoErrors();
        $this->assertSame(0, ScenarioImplication::count());
    }

    public function test_narrative_implications_text_becomes_an_untyped_proposal_and_stays_visible(): void
    {
        $this->readyFramework();
        $this->quadrant('HL')->update(['implications' => 'Organisasi perlu bermitra dengan produsen sel baterai.']);

        $this->assertSame(1, ScenarioImplicationService::importNarrativeImplications());
        $this->assertSame(0, ScenarioImplicationService::importNarrativeImplications());

        $item = ScenarioImplication::sole();
        $this->assertSame([null, 'proposed', 'migrated'], [$item->type, $item->review_status, $item->origin]);
        $this->assertSame('Organisasi perlu bermitra dengan produsen sel baterai.', $item->description);

        // Giving it a type accepts it.
        $this->actingAs($this->manager)->put(route('scenario-planning.implications.update', [$this->project, $item]), ['type' => 'opportunity', 'title' => 'Kemitraan sel baterai'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['opportunity', 'accepted'], [$item->fresh()->type, $item->fresh()->review_status]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.implications.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Implications')
                ->has('quadrants', 4)
                ->where('quadrants.1.position', 'HL')
                ->where('quadrants.1.narrative_implications', 'Organisasi perlu bermitra dengan produsen sel baterai.')
                ->where('quadrants.1.items.0.title', 'Kemitraan sel baterai')
                ->has('forces', 4)
                ->where('canManage', false));
    }

    public function test_step_seven_needs_an_opportunity_and_a_challenge_in_every_quadrant(): void
    {
        $this->readyFramework();
        $step = fn () => $this->actingAs($this->manager)->get(route('scenario-planning.show', $this->project));

        $step()->assertInertia(fn ($page) => $page->where('steps.6.status', 'available'));
        foreach (Scenario::POSITIONS as $position) {
            $this->addItem($position, ['type' => 'opportunity', 'title' => "Peluang {$position}"]);
        }
        $step()->assertInertia(fn ($page) => $page->where('steps.6.status', 'in_progress'));

        foreach (Scenario::POSITIONS as $position) {
            $this->addItem($position, ['type' => 'challenge', 'title' => "Tantangan {$position}"]);
        }
        $step()->assertInertia(fn ($page) => $page->where('steps.6.status', 'done'));
    }

    private function draftItem(string $position, string $type, string $title, array $refs): array
    {
        return ['position' => $position, 'type' => $type, 'title' => $title, 'description' => "{$title}.", 'impact_level' => 'high', 'condition_refs' => $refs];
    }

    /** Roadmap R2.6 (implications-v1): AI drafts opportunities and challenges per quadrant, each citing a condition of that quadrant. */
    public function test_ai_drafts_implications_citing_conditions_reviewed_one_by_one(): void
    {
        $this->readyFramework();
        $battery = $this->condition('HH', 'Harga baterai turun 40%.');
        $subsidy = $this->condition('LL', 'Subsidi EV dihentikan.');
        $fake = $this->fakeLlm();
        $fake->respond(['items' => [
            $this->draftItem('HH', 'opportunity', 'Ekspor paket baterai', ['C1']),
            $this->draftItem('HH', 'challenge', 'Perang harga produsen baru', ['C1']),
            $this->draftItem('LL', 'challenge', 'Permintaan EV turun', ['C2']),
        ]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.implications.suggest', $this->project))->assertSessionHasNoErrors();

        $this->assertSame('scenario_implications', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('Harga baterai turun 40%.', $fake->lastRequest()->user);
        $this->assertSame(3, ScenarioImplication::where('origin', 'ai')->where('review_status', 'proposed')->count());
        $export = ScenarioImplication::where('title', 'Ekspor paket baterai')->sole();
        $this->assertSame([$battery->id], $export->conditions()->pluck('scenario_conditions.id')->all());
        $this->assertSame([$subsidy->id], ScenarioImplication::where('title', 'Permintaan EV turun')->sole()->conditions()->pluck('scenario_conditions.id')->all());
        $this->assertSame('completed', AiGenerationRun::sole()->status);

        $this->actingAs($this->viewer)->get(route('scenario-planning.implications.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('implicationStatus', 'completed')->where('quadrants.0.items.0.review_status', 'proposed'));

        $this->actingAs($this->manager)->post(route('scenario-planning.implications.accept', [$this->project, $export]))->assertSessionHasNoErrors();
        $this->assertSame('accepted', $export->fresh()->review_status);

        $fake->respond(['items' => [$this->draftItem('LL', 'opportunity', 'Pasar mobil bekas', ['C2'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Pasar mobil bekas'], ScenarioImplication::where('review_status', 'proposed')->pluck('title')->all());
        $this->assertSame(['Ekspor paket baterai'], ScenarioImplication::where('review_status', 'accepted')->pluck('title')->all());
    }

    public function test_ai_implication_drafts_are_checked_and_follow_the_rules(): void
    {
        $this->approveAxes();
        $this->createFramework();
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.suggest', $this->project))->assertSessionHasErrors('ai');
        $this->approveFramework()->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.suggest', $this->project))->assertSessionHasErrors('ai');

        $this->condition('HH', 'Harga baterai turun 40%.');
        $this->condition('LL', 'Subsidi EV dihentikan.');
        $this->actingAs($this->viewer)->post(route('scenario-planning.implications.suggest', $this->project))->assertForbidden();

        // An item citing a condition of another quadrant rejects the run.
        $this->fakeLlm()->respond(['items' => [$this->draftItem('HH', 'challenge', 'Salah kuadran', ['C2'])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, ScenarioImplication::count());
        $this->assertSame('rejected', AiGenerationRun::sole()->status);

        $this->fakeLlm()->respond(['items' => [$this->draftItem('HH', 'challenge', 'Tanpa rujukan', [])]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.implications.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertSame(0, ScenarioImplication::count());
    }

    /** Roadmap R2.6 (generic check): an item close to an item of another quadrant does not tell the futures apart. */
    public function test_items_alike_across_quadrants_are_flagged_as_generic(): void
    {
        $this->readyFramework();
        $this->addItem('HH', ['type' => 'opportunity', 'title' => 'Pangsa pasar xEV naik tajam']);
        $this->addItem('HL', ['type' => 'opportunity', 'title' => 'Pangsa pasar xEV naik tajam sekali']);
        $this->addItem('LL', ['type' => 'challenge', 'title' => 'Subsidi dicabut pemerintah']);

        $this->actingAs($this->viewer)->get(route('scenario-planning.implications.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('quadrants.0.items.0.generic', true)
                ->where('quadrants.0.items.0.similar_to', ['HL'])
                ->where('quadrants.1.items.0.similar_to', ['HH'])
                ->where('quadrants.3.items.0.generic', false));
    }
}
