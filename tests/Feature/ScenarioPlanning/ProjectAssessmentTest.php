<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\AiGenerationRun;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R1.1: impact and uncertainty (1–10) with rationale are scored per
 * exercise, shown on the exercise's own matrix, and agreed as gate G3.
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 3.
 */
class ProjectAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private Dimension $legislative;

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
    }

    private function project(bool $agreeForces = true, array $forces = []): ScenarioPlanningProject
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Elektrifikasi 2030',
            'focal_question' => 'How Indonesia vehicle electrification look like in 2030?',
            'purpose' => 'Strategic proposal toward 2030.',
            'target_year' => now()->year + 4,
        ]);
        $project = ScenarioPlanningProject::latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project));
        if ($forces !== []) {
            $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $project), ['forces' => array_map(fn ($f) => $f->uuid, $forces)]);
        }
        if ($agreeForces) {
            $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        }

        return $project->fresh();
    }

    private function force(string $keyword, ?array $rating = null): DrivingForce
    {
        $force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $this->legislative->id, 'created_by' => $this->manager->id,
            'keyword' => $keyword, 'description' => "{$keyword}.", 'status' => 'APPROVED',
        ]);
        if ($rating) {
            $horizon = TimeHorizon::firstOrCreate(['code' => 'MT'], ['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term']);
            DrivingForceRating::create(['driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'impact_analysis' => $rating[0], 'uncertainty_analysis' => $rating[1]]);
        }

        return $force;
    }

    private function link(ScenarioPlanningProject $project, DrivingForce $force): ProjectDrivingForce
    {
        return ProjectDrivingForce::where('project_id', $project->id)->where('driving_force_id', $force->id)->sole();
    }

    private function score(ScenarioPlanningProject $project, DrivingForce $force, array $values)
    {
        return $this->actingAs($this->manager)->put(route('scenario-planning.assessment.update', [$project, $this->link($project, $force)]), $values);
    }

    public function test_manager_scores_an_included_force_with_rationale(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(forces: [$force]);

        $this->score($project, $force, [
            'impact' => 9, 'uncertainty' => 8,
            'impact_rationale' => 'Menentukan akses insentif.', 'uncertainty_rationale' => 'Aturan turunan belum terbit.',
        ])->assertSessionHasNoErrors();

        $assessment = ForceAssessment::sole();
        $this->assertSame('consensus', $assessment->kind);
        $this->assertSame('human', $assessment->origin);
        $this->assertSame([9, 8], [$assessment->impact, $assessment->uncertainty]);
        $this->assertSame('Aturan turunan belum terbit.', $assessment->uncertainty_rationale);
        $this->assertSame($this->manager->id, $assessment->updated_by);
    }

    public function test_scores_are_validated_and_need_the_agreed_force_list(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(agreeForces: false, forces: [$force]);

        $this->score($project, $force, ['impact' => 9, 'uncertainty' => 8])->assertSessionHasErrors('impact');
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        $this->score($project, $force, ['impact' => 11, 'uncertainty' => 0])->assertSessionHasErrors(['impact', 'uncertainty']);

        $this->assertSame(0, ForceAssessment::count());
    }

    public function test_an_excluded_force_cannot_be_scored(): void
    {
        $kept = $this->force('TKDN Regulation');
        $dropped = $this->force('Import Regulation');
        $project = $this->project(agreeForces: false, forces: [$kept, $dropped]);
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $dropped)]), ['inclusion_status' => 'excluded']);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));

        $this->score($project, $dropped, ['impact' => 5, 'uncertainty' => 5])->assertSessionHasErrors('impact');
        $this->assertSame(0, ForceAssessment::count());
    }

    public function test_adding_a_force_copies_its_organization_rating_for_review(): void
    {
        $rated = $this->force('TKDN Regulation', [8, 9]);
        $unrated = $this->force('Solid Battery');
        $project = $this->project(forces: [$rated, $unrated]);

        $assessment = ForceAssessment::sole();
        $this->assertSame($this->link($project, $rated)->id, $assessment->project_driving_force_id);
        $this->assertSame('library', $assessment->origin);
        $this->assertSame([8, 9], [$assessment->impact, $assessment->uncertainty]);
    }

    public function test_project_scores_never_change_the_organization_rating(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $project = $this->project(forces: [$force]);

        $this->score($project, $force, ['impact' => 3, 'uncertainty' => 2])->assertSessionHasNoErrors();

        $rating = DrivingForceRating::where('driving_force_id', $force->id)->sole();
        $this->assertSame([8, 9], [$rating->impact_analysis, $rating->uncertainty_analysis]);
        $this->assertSame('human', ForceAssessment::sole()->origin);
    }

    public function test_the_page_plots_only_included_forces_numbered_in_method_order(): void
    {
        $a = $this->force('Import Regulation', [6, 4]);
        $b = $this->force('TKDN Regulation', [8, 9]);
        $c = $this->force('Euro-5 Plan');
        $project = $this->project(agreeForces: false, forces: [$a, $b, $c]);
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $c)]), ['inclusion_status' => 'excluded']);

        $this->actingAs($this->viewer)->get(route('scenario-planning.assessment.index', $project))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Assessment')
                ->has('forces', 2)
                ->where('forces.0.number', 1)
                ->where('forces.0.force.keyword', 'Import Regulation')
                ->where('forces.1.impact', 8)
                ->where('forces.1.uncertainty', 9)
                ->where('forces.1.origin', 'library'));
    }

    public function test_agreeing_scores_needs_every_included_force_scored(): void
    {
        $scored = $this->force('TKDN Regulation', [8, 9]);
        $unscored = $this->force('Solid Battery');
        $project = $this->project(forces: [$scored, $unscored]);

        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project))->assertSessionHasErrors('scores');
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.2.status', 'in_progress')->where('steps.3.status', 'locked'));

        $this->score($project, $unscored, ['impact' => 7, 'uncertainty' => 8]);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project))->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame($this->manager->id, $project->scores_confirmed_by);
        // Step 3 finishes, and step 4 opens, only when two critical uncertainties are approved (R0.4).
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.2.status', 'in_progress')->where('steps.3.status', 'locked'));
    }

    public function test_changing_a_score_after_agreement_withdraws_it_but_a_rationale_edit_does_not(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $project = $this->project(forces: [$force]);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project));

        $this->score($project, $force, ['impact' => 8, 'uncertainty' => 9, 'impact_rationale' => 'Lebih jelas.']);
        $this->assertNotNull($project->fresh()->scores_confirmed_at);

        $this->score($project, $force, ['impact' => 6, 'uncertainty' => 9])->assertSessionHas('success');
        $this->assertNull($project->fresh()->scores_confirmed_at);
    }

    public function test_withdrawing_the_force_list_agreement_also_withdraws_the_score_agreement(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $other = $this->force('Solid Battery', [5, 5]);
        $project = $this->project(forces: [$force, $other]);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project));

        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $other)]), ['inclusion_status' => 'excluded']);

        $project->refresh();
        $this->assertNull($project->forces_confirmed_at);
        $this->assertNull($project->scores_confirmed_at);
    }

    public function test_viewers_and_non_owners_cannot_score(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(forces: [$force]);
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);
        $link = $this->link($project, $force);

        $this->actingAs($this->viewer)->put(route('scenario-planning.assessment.update', [$project, $link]), ['impact' => 5, 'uncertainty' => 5])->assertForbidden();
        $this->actingAs($colleague)->put(route('scenario-planning.assessment.update', [$project, $link]), ['impact' => 5, 'uncertainty' => 5])->assertForbidden();
        $this->assertSame(0, ForceAssessment::count());
    }

    private function mine(User $user, ScenarioPlanningProject $project, DrivingForce $force, array $values)
    {
        return $this->actingAs($user)->put(route('scenario-planning.assessment.mine', [$project, $this->link($project, $force)]), $values);
    }

    /** Roadmap R2.1 (slide 14): each participant scores alone; the spread is shown; the consensus row stays distinct. */
    public function test_two_participants_score_the_same_force_and_the_spread_is_shown(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(forces: [$force]);

        $this->mine($this->viewer, $project, $force, ['impact' => 9, 'uncertainty' => 3, 'impact_rationale' => 'Akses insentif.'])->assertSessionHasNoErrors();
        $this->mine($this->manager, $project, $force, ['impact' => 6, 'uncertainty' => 8])->assertSessionHasNoErrors();

        $this->assertSame(2, ForceAssessment::where('kind', 'individual')->count());
        $this->assertSame(0, ForceAssessment::where('kind', 'consensus')->count());
        $this->assertEqualsCanonicalizing([$this->viewer->id, $this->manager->id], ForceAssessment::pluck('assessor_id')->all());

        $this->actingAs($this->viewer)->get(route('scenario-planning.assessment.index', $project))
            ->assertInertia(fn ($page) => $page
                ->where('forces.0.impact', null)
                ->where('forces.0.individual.count', 2)
                ->where('forces.0.individual.impact', ['min' => 6, 'max' => 9, 'median' => 7.5])
                ->where('forces.0.individual.uncertainty', ['min' => 3, 'max' => 8, 'median' => 5.5])
                ->where('forces.0.individual.wide', true)
                ->has('forces.0.individual.assessors', 2)
                ->where('forces.0.mine.impact', 9)
                ->where('forces.0.mine.impact_rationale', 'Akses insentif.')
                ->where('canScore', true));

        $this->score($project, $force, ['impact' => 8, 'uncertainty' => 6])->assertSessionHasNoErrors();
        $this->assertSame([8, 6], [ForceAssessment::where('kind', 'consensus')->sole()->impact, ForceAssessment::where('kind', 'consensus')->sole()->uncertainty]);
        $this->assertSame(2, ForceAssessment::where('kind', 'individual')->count());
    }

    public function test_a_participant_edits_and_withdraws_only_their_own_score(): void
    {
        $force = $this->force('TKDN Regulation');
        $project = $this->project(forces: [$force]);
        $this->mine($this->viewer, $project, $force, ['impact' => 9, 'uncertainty' => 3]);
        $this->mine($this->manager, $project, $force, ['impact' => 6, 'uncertainty' => 8]);

        $this->mine($this->viewer, $project, $force, ['impact' => 7, 'uncertainty' => 4])->assertSessionHasNoErrors();
        $this->assertSame(7, ForceAssessment::where('assessor_id', $this->viewer->id)->sole()->impact);
        $this->assertSame(2, ForceAssessment::count());

        $this->actingAs($this->viewer)->delete(route('scenario-planning.assessment.withdraw', [$project, $this->link($project, $force)]))->assertSessionHasNoErrors();
        $this->assertSame([$this->manager->id], ForceAssessment::pluck('assessor_id')->all());

        $this->actingAs($this->viewer)->get(route('scenario-planning.assessment.index', $project))
            ->assertInertia(fn ($page) => $page->where('forces.0.mine', null)->where('forces.0.individual.count', 1)->where('forces.0.individual.wide', false));
    }

    public function test_individual_scores_never_touch_the_agreement_and_follow_the_same_rules(): void
    {
        $force = $this->force('TKDN Regulation', [8, 9]);
        $excluded = $this->force('Solid Battery', [5, 5]);
        $project = $this->project(agreeForces: false, forces: [$force, $excluded]);

        $this->mine($this->viewer, $project, $force, ['impact' => 9, 'uncertainty' => 3])->assertSessionHasErrors('impact');
        $this->actingAs($this->manager)->put(route('scenario-planning.forces.update', [$project, $this->link($project, $excluded)]), ['inclusion_status' => 'excluded']);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $project));
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.confirm', $project))->assertSessionHasNoErrors();

        $this->mine($this->viewer, $project, $force, ['impact' => 11, 'uncertainty' => 3])->assertSessionHasErrors('impact');
        $this->mine($this->viewer, $project, $excluded, ['impact' => 5, 'uncertainty' => 5])->assertSessionHasErrors('impact');
        $this->mine($this->viewer, $project, $force, ['impact' => 2, 'uncertainty' => 2])->assertSessionHasNoErrors();

        $this->assertNotNull($project->fresh()->scores_confirmed_at);
        $this->assertSame([8, 9], [$this->link($project, $force)->consensus->impact, $this->link($project, $force)->consensus->uncertainty]);

        $outsider = User::factory()->create();
        $this->mine($outsider, $project, $force, ['impact' => 5, 'uncertainty' => 5])->assertForbidden();
        $this->assertSame(1, ForceAssessment::where('kind', 'individual')->count());
    }

    private function acceptedSignal(DrivingForce $force, string $title): Signal
    {
        $raw = "{$title} raw";
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(), 'title' => "{$title} source", 'url' => 'https://example.test/'.md5($title),
            'raw_content' => $raw, 'content_hash' => hash('sha256', $raw), 'created_by' => $this->manager->id,
        ]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'dimension_id' => $this->legislative->id,
            'title' => $title, 'summary' => "{$title}.", 'evidence_quote' => "{$title}.", 'confidence_score' => 0.9, 'review_status' => 'ACCEPTED',
        ]);
        $force->supporting_signals()->attach($signal->id);

        return $signal;
    }

    private function suggestion(string $force, int $impact, int $uncertainty, array $refs): array
    {
        return ['force' => $force, 'impact' => $impact, 'uncertainty' => $uncertainty, 'impact_rationale' => "Impact {$impact}.", 'uncertainty_rationale' => "Uncertainty {$uncertainty}.", 'cited_refs' => $refs];
    }

    /** Roadmap R2.4 (score suggestions): AI proposes scores citing only offered signals; nothing applies until accepted. */
    public function test_ai_suggests_scores_and_nothing_applies_until_accepted(): void
    {
        $alpha = $this->force('Alpha Rule');
        $beta = $this->force('Beta Rule');
        $this->acceptedSignal($alpha, 'Insentif TKDN diperluas');
        $project = $this->project(forces: [$alpha, $beta]);
        $fake = $this->fakeLlm();
        $fake->respond(['suggestions' => [$this->suggestion('F1', 9, 8, ['S1']), $this->suggestion('F2', 5, 4, [])]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.suggest', $project))->assertSessionHasNoErrors();

        $this->assertSame('force_assessment', $fake->lastRequest()->purpose);
        $this->assertStringContainsString('Insentif TKDN diperluas', $fake->lastRequest()->user);
        $this->assertStringContainsString($project->focal_question, $fake->lastRequest()->user);
        $this->assertSame(2, ForceAssessment::where('kind', 'ai_suggestion')->where('origin', 'ai')->count());
        $this->assertSame(0, ForceAssessment::where('kind', 'consensus')->count());
        $run = AiGenerationRun::sole();
        $this->assertSame(['force_assessment', 'completed'], [$run->purpose, $run->status]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.assessment.index', $project))
            ->assertInertia(fn ($page) => $page
                ->where('forces.0.force.keyword', 'Alpha Rule')
                ->where('forces.0.impact', null)
                ->where('forces.0.suggestion.impact', 9)
                ->where('forces.0.suggestion.uncertainty', 8)
                ->where('forces.0.suggestion.signals.0.title', 'Insentif TKDN diperluas')
                ->where('forces.1.suggestion.signals', [])
                ->where('suggestionStatus', 'completed'));

        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.suggestion.accept', [$project, $this->link($project, $alpha)]))->assertSessionHasNoErrors();
        $consensus = $this->link($project, $alpha)->consensus;
        $this->assertSame([9, 8, 'ai', 'Impact 9.'], [$consensus->impact, $consensus->uncertainty, $consensus->origin, $consensus->impact_rationale]);

        $this->actingAs($this->manager)->delete(route('scenario-planning.assessment.suggestion.reject', [$project, $this->link($project, $beta)]))->assertSessionHasNoErrors();
        $this->assertSame(0, ForceAssessment::where('kind', 'ai_suggestion')->count());
        $this->assertNull($this->link($project, $beta)->consensus);
    }

    public function test_score_suggestions_citing_unknown_refs_are_rejected_and_rules_apply(): void
    {
        $alpha = $this->force('Alpha Rule');
        $this->acceptedSignal($alpha, 'Insentif TKDN diperluas');
        $unagreed = $this->project(agreeForces: false, forces: [$alpha]);
        $this->fakeLlm()->respond(['suggestions' => [$this->suggestion('F1', 9, 8, ['S9'])]]);

        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.suggest', $unagreed))->assertSessionHasErrors('ai');
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $unagreed));
        $this->actingAs($this->viewer)->post(route('scenario-planning.assessment.suggest', $unagreed))->assertForbidden();

        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.suggest', $unagreed))->assertSessionHasNoErrors();
        $this->assertSame(0, ForceAssessment::where('kind', 'ai_suggestion')->count());
        $this->assertSame('rejected', AiGenerationRun::sole()->status);
        $this->actingAs($this->manager)->get(route('scenario-planning.assessment.index', $unagreed))
            ->assertInertia(fn ($page) => $page->where('suggestionStatus', 'failed')->where('suggestionError', fn ($error) => str_contains((string) $error, 'S9')));

        $this->fakeLlm(available: false);
        $this->actingAs($this->manager)->post(route('scenario-planning.assessment.suggest', $unagreed))->assertSessionHasErrors('ai');
    }
}
