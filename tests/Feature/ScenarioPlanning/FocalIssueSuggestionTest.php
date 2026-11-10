<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\AiGenerationRun;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R3.1 (focal-issue-v1): a queued AI critique of the focal question with alternative
 * questions. Nothing is written to the exercise: picking an alternative only prefills the edit
 * dialog, and saving goes through the normal update (reconfirmation after G1).
 */
class FocalIssueSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private ScenarioPlanningProject $project;

    private const QUESTION = 'Apakah elektrifikasi kendaraan akan berhasil?';

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

        $this->actingAs($this->manager)->post(route('scenario-planning.store'), $this->payload())->assertSessionHasNoErrors();
        $this->project = ScenarioPlanningProject::latest('id')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Elektrifikasi 2030',
            'focal_question' => self::QUESTION,
            'purpose' => 'Menyusun usulan strategis menuju elektrifikasi 2030.',
            'target_year' => now()->year + 4,
            'geography' => 'Indonesia',
        ];
    }

    private function suggestion(array $overrides = []): array
    {
        return $overrides + [
            'critique' => [
                ['issue' => 'yes_no', 'comment' => 'Pertanyaan ya/tidak menutup ruang skenario.'],
                ['issue' => 'no_horizon', 'comment' => 'Tahun target tidak disebut.'],
            ],
            'alternatives' => [
                ['question' => 'Seperti apa lanskap elektrifikasi kendaraan roda empat di Indonesia pada 2030?', 'rationale' => 'Terbuka dan menyebut cakupan serta horizon.'],
                ['question' => 'Bagaimana kebijakan dan teknologi membentuk adopsi EV di Indonesia hingga 2030?', 'rationale' => 'Menyoroti dua penggerak utama.'],
            ],
        ];
    }

    public function test_ai_critiques_the_focal_question_without_changing_the_exercise(): void
    {
        $fake = $this->fakeLlm();
        $fake->respond($this->suggestion());

        $this->actingAs($this->manager)->post(route('scenario-planning.focal.suggest', $this->project))->assertSessionHasNoErrors();

        $this->assertSame('scenario_focal_issue', $fake->lastRequest()->purpose);
        $this->assertStringContainsString(self::QUESTION, $fake->lastRequest()->user);
        $this->assertStringContainsString('Indonesia', $fake->lastRequest()->user);
        $this->assertSame(self::QUESTION, $this->project->fresh()->focal_question);
        $run = AiGenerationRun::sole();
        $this->assertSame(['completed', 'focal-issue-v1'], [$run->status, $run->prompt_version]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('focalStatus', 'completed')
                ->where('focalSuggestion.critique.0.issue', 'yes_no')
                ->where('focalSuggestion.alternatives.1.question', 'Bagaimana kebijakan dan teknologi membentuk adopsi EV di Indonesia hingga 2030?'));

        $this->actingAs($this->manager)->delete(route('scenario-planning.focal.suggestion.reject', $this->project))->assertSessionHasNoErrors();
        $this->assertNull($this->project->fresh()->focal_suggestion);
    }

    public function test_saving_a_new_focal_question_drops_the_suggestion(): void
    {
        $this->fakeLlm()->respond($this->suggestion());
        $this->actingAs($this->manager)->post(route('scenario-planning.focal.suggest', $this->project));
        $this->assertNotNull($this->project->fresh()->focal_suggestion);

        $this->actingAs($this->manager)->put(route('scenario-planning.update', $this->project), $this->payload(['name' => 'Elektrifikasi 2030 (rev)']))->assertSessionHasNoErrors();
        $this->assertNotNull($this->project->fresh()->focal_suggestion);

        $this->actingAs($this->manager)->put(route('scenario-planning.update', $this->project), $this->payload(['focal_question' => 'Seperti apa lanskap elektrifikasi kendaraan roda empat di Indonesia pada 2030?']))->assertSessionHasNoErrors();
        $this->assertNull($this->project->fresh()->focal_suggestion);
    }

    public function test_suggestions_are_checked_and_need_a_coordinator_and_ai(): void
    {
        $this->actingAs($this->viewer)->post(route('scenario-planning.focal.suggest', $this->project))->assertForbidden();

        $this->fakeLlm(available: false);
        $this->actingAs($this->manager)->post(route('scenario-planning.focal.suggest', $this->project))->assertSessionHasErrors('ai');

        $fake = $this->fakeLlm();
        $fake->respond($this->suggestion(['alternatives' => [['question' => self::QUESTION, 'rationale' => 'Sama.']]]));
        $this->actingAs($this->manager)->post(route('scenario-planning.focal.suggest', $this->project))->assertSessionHasNoErrors();
        $this->assertNull($this->project->fresh()->focal_suggestion);
        $this->assertSame('failed', $this->project->fresh()->focal_suggestion_status);
        $this->assertSame('rejected', AiGenerationRun::sole()->status);
    }
}
