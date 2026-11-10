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
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Signal;
use App\Models\Source;
use App\Models\StrategicResponse;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R4.2 (deck slide 26): a reviewer classifies a signal by horizon (likely: current trend,
 * could: current knowledge, might: weak signal / future knowledge) and flags wild cards. The signal
 * list filters by class, and step 9 lists accepted weak signals and wild cards apart (sensing).
 */
class SignalClassificationTest extends TestCase
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

        foreach (['view-scenario', 'manage-scenario', 'view-signal', 'review-signal'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'BOD'])->givePermissionTo('view-scenario');
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['view-scenario', 'manage-scenario', 'view-signal', 'review-signal']);
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

    private function signal(string $title, string $status = 'ACCEPTED', array $attributes = []): Signal
    {
        $raw = "{$title} raw text";
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => "{$title} source", 'url' => 'https://example.test/'.md5($title),
            'raw_content' => $raw, 'content_hash' => hash('sha256', $raw), 'created_by' => $this->manager->id,
        ]);

        return Signal::create($attributes + [
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'dimension_id' => Dimension::where('code', 'TECHNOLOGY')->value('id'),
            'title' => $title, 'summary' => "{$title}.", 'evidence_quote' => "{$title}.", 'confidence_score' => 0.9, 'review_status' => $status,
        ]);
    }

    private function classify(Signal $signal, array $values, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)->postJson(route('signals.classify', $signal->uuid), $values);
    }

    public function test_a_reviewer_classifies_a_signal_by_horizon_and_wild_card(): void
    {
        $signal = $this->signal('Solid-state battery pilot line in China', 'PENDING');

        $this->classify($signal, ['horizon' => 'might', 'wild_card' => false])->assertOk();
        $signal->refresh();
        $this->assertSame(['might', false, $this->manager->id], [$signal->horizon, $signal->wild_card, $signal->classified_by]);
        $this->assertNotNull($signal->classified_at);

        // An accepted signal can be classified again as evidence develops; the class can be cleared.
        $signal->update(['review_status' => 'ACCEPTED']);
        $this->classify($signal, ['horizon' => null, 'wild_card' => true])->assertOk();
        $this->assertSame([null, true], [$signal->fresh()->horizon, $signal->fresh()->wild_card]);

        $this->classify($signal, ['horizon' => 'maybe'])->assertJsonValidationErrors('horizon');
        $this->classify($signal, ['horizon' => 'likely'], $this->viewer)->assertForbidden();
        $this->assertSame(null, $signal->fresh()->horizon);
    }

    public function test_the_signal_list_filters_by_class(): void
    {
        $this->signal('EV sales keep rising', 'PENDING', ['horizon' => 'likely']);
        $this->signal('Sodium-ion breakthrough', 'PENDING', ['horizon' => 'might']);
        $this->signal('Nickel export ban reversed', 'PENDING', ['horizon' => 'could', 'wild_card' => true]);
        $this->signal('Unclassified news', 'PENDING');

        $titles = fn (string $sensing) => collect($this->actingAs($this->manager)->getJson(route('signals.fetch', ['status' => 'ALL', 'sensing' => $sensing]))->assertOk()->json('data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Sodium-ion breakthrough'], $titles('might'));
        $this->assertSame(['Nickel export ban reversed'], $titles('wild_card'));
        $this->assertSame(['Unclassified news'], $titles('none'));
        $this->assertCount(4, $titles('all'));
        $row = collect($this->actingAs($this->manager)->getJson(route('signals.fetch', ['status' => 'ALL', 'sensing' => 'wild_card']))->json('data'))->first();
        $this->assertSame(['could', true], [$row['horizon'], $row['wild_card']]);
    }

    public function test_step_nine_lists_accepted_weak_signals_and_wild_cards_apart(): void
    {
        $this->readyFramework();
        $this->signal('Sodium-ion breakthrough', 'ACCEPTED', ['horizon' => 'might']);
        $this->signal('Nickel export ban reversed', 'ACCEPTED', ['horizon' => 'could', 'wild_card' => true]);
        $this->signal('EV sales keep rising', 'ACCEPTED', ['horizon' => 'likely']);
        $this->signal('Pending weak signal', 'PENDING', ['horizon' => 'might']);
        $this->signal('Rejected wild card', 'REJECTED', ['wild_card' => true]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.indicators.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->count('sensing', 2)
                ->where('sensing', fn ($rows) => collect($rows)->pluck('title')->sort()->values()->all() === ['Nickel export ban reversed', 'Sodium-ion breakthrough'])
                ->where('sensing.0.horizon', fn ($value) => in_array($value, ['might', 'could'], true)));
    }
}
