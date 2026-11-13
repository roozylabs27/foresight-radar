<?php

namespace Tests\Feature\Llm;

use App\Jobs\GenerateScenarioNarrative;
use App\Models\AiGenerationRun;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Scenario;
use App\Models\ScenarioCitation;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TenantLlmSetting;
use App\Models\TenantLlmUsage;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

class ScenarioTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private Environment $environment;

    private TimeHorizon $horizon;

    private DrivingForce $regulation;

    private DrivingForce $adoption;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]
            );
        }
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo('view-scenario');

        $this->environment = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $this->horizon = TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        $this->regulation = $this->force('TKDN Regulation', 'Regulation', 9, 8);
        $this->adoption = $this->force('EV Adoption', 'Customer', 8, 9);
    }

    private function force(string $keyword, string $dimensionName, ?int $impact, ?int $uncertainty, string $status = 'APPROVED'): DrivingForce
    {
        $dimension = Dimension::firstOrCreate(['name' => $dimensionName], ['uuid' => (string) Uuid::uuid4(), 'environment_id' => $this->environment->id]);
        $force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => $keyword,
            'description' => 'Driving force for scenario tests.',
            'status' => $status,
            'dimension_id' => $dimension->id,
            'pic' => $this->manager->id,
            'created_by' => $this->manager->id,
        ]);
        DrivingForceRating::create([
            'driving_force_id' => $force->id,
            'time_horizon_id' => $this->horizon->id,
            'impact_analysis' => $impact,
            'uncertainty_analysis' => $uncertainty,
        ]);

        return $force;
    }

    private function acceptedSignal(DrivingForce $force, string $title): Signal
    {
        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => "Report on {$title}",
            'raw_content' => "{$title} is described in this report in detail.",
            'content_hash' => hash('sha256', $title),
            'created_by' => $this->manager->id,
        ]);
        $signal = Signal::create([
            'uuid' => (string) Uuid::uuid4(),
            'source_id' => $source->id,
            'title' => $title,
            'summary' => "Summary of {$title}.",
            'evidence_quote' => "{$title} is described in this report in detail.",
            'review_status' => 'ACCEPTED',
            'reviewed_at' => now(),
            'quote_verified' => true,
        ]);
        $signal->driving_forces()->attach($force->id);

        return $signal;
    }

    /**
     * PAD-2: stand-alone scenarios are a read-only archive, so the narrative engine is exercised through an
     * approved exercise framework (same service; the exercise policy decides who may act).
     */
    private function scenario(): Scenario
    {
        $project = ScenarioPlanningProject::create([
            'name' => 'EV 2030', 'focal_question' => 'Bagaimana pasar EV pada 2030?', 'purpose' => 'Uji mesin narasi.',
            'target_year' => now()->year + 4, 'status' => 'active', 'confirmed_by' => $this->manager->id, 'confirmed_at' => now(),
            'created_by' => $this->manager->id,
        ]);

        $framework = $this->standAlone(['project_id' => $project->id, 'framework_status' => 'approved', 'approved_by' => $this->manager->id, 'approved_at' => now()]);
        // As ScenarioFrameworkService does: four quadrants named after their poles, locked at G5.
        foreach (Scenario::POSITIONS as $position) {
            $framework->quadrants()->create(['position' => $position, 'title' => $framework->defaultTitle($position)]);
        }

        return $framework;
    }

    private function standAlone(array $overrides = []): Scenario
    {
        return Scenario::create($overrides + [
            'name' => 'EV 2030',
            'focal_question' => 'Bagaimana pasar EV pada 2030?',
            'axis_a_force_id' => $this->regulation->id,
            'axis_b_force_id' => $this->adoption->id,
            'axis_a_low_label' => 'Regulasi longgar',
            'axis_a_high_label' => 'Regulasi ketat',
            'axis_b_low_label' => 'Adopsi lambat',
            'axis_b_high_label' => 'Adopsi cepat',
            'created_by' => $this->manager->id,
        ]);
    }

    private function narrative(array $citations): array
    {
        return ['quadrants' => array_map(fn ($position) => [
            'position' => $position,
            'title' => "Masa depan {$position}",
            'narrative' => "Narasi {$position} ".implode(' ', array_map(fn ($ref) => "[{$ref}]", $citations[$position] ?? [])),
            'implications' => "Implikasi {$position}.",
            'early_indicators' => ["Indikator {$position}"],
            'cited_refs' => $citations[$position] ?? [],
        ], Scenario::POSITIONS)];
    }

    private function generate(Scenario $scenario, array $body = [])
    {
        return $this->actingAs($this->manager)->postJson("/scenarios/{$scenario->uuid}/generate", $body);
    }

    /**
     * PAD-2 (2026-11-13): stand-alone scenarios were made outside the nine-step method, so they are a
     * read-only archive: viewable, but nobody (not even their creator or an admin) creates, edits,
     * regenerates, reviews or deletes one. Scenarios are now made inside an exercise.
     */
    public function test_stand_alone_scenarios_are_a_read_only_archive(): void
    {
        $archived = $this->standAlone(['created_by' => $this->manager->id]);
        $quadrant = $archived->quadrants()->create(['position' => 'HH', 'title' => 'Lama', 'narrative' => 'Narasi lama.']);
        $admin = User::factory()->create();
        $admin->givePermissionTo(['view-scenario', 'manage-scenario']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin']);
        $admin->assignRole('admin');

        $this->actingAs($this->viewer)->get('/scenarios')->assertStatus(200)->assertInertia(fn ($page) => $page->component('Report/Scenarios'));
        $this->actingAs($this->viewer)->getJson("/scenarios/{$archived->uuid}")->assertStatus(200)->assertJsonPath('name', 'EV 2030');
        $this->actingAs(User::factory()->create())->getJson('/scenarios/fetch')->assertStatus(403);

        // Creation and axis suggestions are gone.
        $this->actingAs($this->manager)->postJson('/scenarios', ['name' => 'Baru'])->assertStatus(405);
        $this->actingAs($this->manager)->putJson("/scenarios/{$archived->uuid}", ['name' => 'Ubah'])->assertStatus(405);
        $this->actingAs($this->manager)->getJson('/scenarios/suggest-axes')->assertNotFound();

        foreach ([$this->manager, $admin] as $user) {
            $this->actingAs($user)->postJson("/scenarios/{$archived->uuid}/generate")->assertForbidden();
            $this->actingAs($user)->putJson("/scenarios/{$archived->uuid}/quadrants/{$quadrant->uuid}", ['title' => 'Baru', 'narrative' => 'Baru.'])->assertForbidden();
            $this->actingAs($user)->postJson("/scenarios/{$archived->uuid}/quadrants/{$quadrant->uuid}/review", ['status' => 'approved'])->assertForbidden();
            $this->actingAs($user)->deleteJson("/scenarios/{$archived->uuid}")->assertForbidden();
        }
        $this->assertSame(['EV 2030', 'Lama', 'draft'], [$archived->fresh()->name, $quadrant->fresh()->title, $quadrant->fresh()->review_status ?? 'draft']);
        $this->assertNull($archived->fresh()->deleted_at);

        // A viewer cannot act on an exercise framework either.
        $framework = $this->scenario();
        $this->actingAs($this->viewer)->postJson("/scenarios/{$framework->uuid}/generate")->assertStatus(403);
    }

    public function test_without_a_key_generate_builds_a_labelled_template(): void
    {
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');

        $response = $this->generate($scenario)->assertStatus(202);

        $response->assertJsonPath('scenario.provider', 'template')
            ->assertJsonPath('scenario.generation.status', 'completed')
            ->assertJsonPath('scenario.generation.message', 'Kerangka empat kuadran dibuat tanpa AI. Lengkapi narasinya bersama tim.');
        $quadrants = collect($response->json('scenario.quadrants'))->keyBy('position');
        $this->assertSame(['LH', 'HH', 'LL', 'HL'], array_values(array_intersect(['LH', 'HH', 'LL', 'HL'], $quadrants->keys()->all())));
        $this->assertCount(4, $quadrants);
        $this->assertSame('Regulasi ketat · Adopsi lambat', $quadrants['HL']['title']);
        $this->assertFalse($quadrants['HL']['is_ai_generated']);
        // Roadmap R0.6: the shared origin field names template output as such.
        $this->assertSame('template', $quadrants['HL']['origin']);
        $this->assertStringStartsWith('Kerangka tanpa AI.', $quadrants['HL']['narrative']);
        $this->assertSame(['S1'], $quadrants['HL']['cited_refs']);
        $this->assertSame('S1', $response->json('scenario.evidence.0.ref'));
    }

    public function test_template_works_even_before_any_evidence_is_linked(): void
    {
        $response = $this->generate($this->scenario())->assertStatus(202);

        $this->assertSame('template', $response->json('scenario.provider'));
        $this->assertStringContainsString('Belum ada sinyal ACCEPTED', $response->json('scenario.quadrants.0.narrative'));
    }

    public function test_claude_narratives_keep_only_offered_citations(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $s1 = $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $s2 = $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $llm->respond($this->narrative([
            'HH' => ['S1', 'S2'],
            'HL' => ['S1', 'S9'],
            'LH' => ['S2'],
            'LL' => ['S2'],
        ]));

        $response = $this->generate($scenario)->assertStatus(202);

        $response->assertJsonPath('scenario.provider', 'claude')->assertJsonPath('scenario.model', 'claude-fake');
        $this->assertStringContainsString('dibuat oleh Claude', $response->json('scenario.generation.message'));
        $quadrants = collect($response->json('scenario.quadrants'))->keyBy('position');
        $this->assertSame(['S1', 'S2'], $quadrants['HH']['cited_refs']);
        $this->assertSame(['S1'], $quadrants['HL']['cited_refs']);
        $this->assertStringNotContainsString('[S9]', $quadrants['HL']['narrative']);
        $this->assertTrue($quadrants['HH']['is_ai_generated']);

        $stored = $scenario->fresh();
        $this->assertSame([$s1->id, $s2->id], $stored->input_signal_ids);
        $this->assertSame('scenario-v3', $stored->prompt_version);
        $this->assertSame([$s1->id], $stored->quadrants()->where('position', 'HL')->first()->cited_signal_ids);

        // The model got the evidence as S-references only, inside the evidence block.
        $user = $llm->lastRequest()->user;
        $this->assertStringContainsString('S1 (axis A): Local content rule enacted', $user);
        $this->assertStringNotContainsString($s1->uuid, $user);
        $this->assertSame(['S1', 'S2'], $llm->lastRequest()->schema['properties']['quadrants']['items']['properties']['cited_refs']['items']['enum']);
    }

    public function test_an_axis_without_evidence_is_rejected_before_calling_the_llm(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');

        $this->generate($scenario)->assertStatus(422)->assertJsonValidationErrors('evidence');

        $this->assertSame([], $llm->calls);
        // An exercise framework keeps its four named quadrants; nothing was written into them.
        $this->assertSame(0, $scenario->quadrants()->whereNotNull('narrative')->count());
    }

    public function test_a_quadrant_without_valid_citation_rejects_the_whole_result(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $llm->respond($this->narrative(['HH' => ['S1'], 'HL' => ['S2'], 'LH' => ['S1'], 'LL' => ['S7']]));

        // The citations are checked in the job, so the request is accepted and the job records why nothing was saved.
        $this->generate($scenario)->assertStatus(202)->assertJsonPath('scenario.generation.status', 'failed');

        // An exercise framework keeps its four named quadrants; nothing was written into them.
        $this->assertSame(0, $scenario->quadrants()->whereNotNull('narrative')->count());
        $this->assertNull($scenario->fresh()->provider);
        $this->assertStringContainsString('kuadran LL tidak mengutip bukti yang valid', $scenario->fresh()->generation_error);
    }

    public function test_refusal_falls_back_to_the_template(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $llm->respond(new LlmException('refusal'));

        $response = $this->generate($scenario)->assertStatus(202);

        $response->assertJsonPath('scenario.provider', 'template')->assertJsonPath('scenario.generation.status', 'completed');
        $this->assertStringContainsString('menolak', $response->json('scenario.generation.notice'));
    }

    public function test_a_failed_regeneration_keeps_the_previous_ai_narrative(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $llm->respond($this->narrative(['HH' => ['S1'], 'HL' => ['S1'], 'LH' => ['S2'], 'LL' => ['S2']]));
        $this->generate($scenario)->assertStatus(202)->assertJsonPath('scenario.provider', 'claude');
        $before = $scenario->quadrants()->orderBy('id')->pluck('narrative', 'id')->all();

        $llm->respond(new LlmException('overloaded'));
        $this->generate($scenario)->assertStatus(202)
            ->assertJsonPath('scenario.generation.status', 'failed')
            ->assertJsonPath('scenario.generation.error', 'Layanan AI sedang sibuk. Coba lagi sebentar lagi. Narasi sebelumnya tidak diubah; coba lagi nanti.')
            ->assertJsonPath('scenario.provider', 'claude');

        $this->assertSame($before, $scenario->quadrants()->orderBy('id')->pluck('narrative', 'id')->all());
        $this->assertSame(4, $scenario->quadrants()->where('is_ai_generated', true)->count());
    }

    public function test_a_failed_regeneration_over_a_template_still_falls_back(): void
    {
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $this->generate($scenario)->assertStatus(202)->assertJsonPath('scenario.provider', 'template');

        $this->fakeLlm()->respond(new LlmException('timeout'));
        $response = $this->generate($scenario)->assertStatus(202)
            ->assertJsonPath('scenario.generation.status', 'completed')
            ->assertJsonPath('scenario.provider', 'template');

        $this->assertStringContainsString('tidak menjawab tepat waktu', $response->json('scenario.generation.notice'));
    }

    public function test_edited_quadrants_are_not_overwritten_without_confirmation(): void
    {
        $scenario = $this->scenario();
        $first = $this->generate($scenario)->json('scenario.quadrants.0');

        $this->actingAs($this->manager)->putJson("/scenarios/{$scenario->uuid}/quadrants/{$first['id']}", [
            'title' => $first['title'], // quadrant names are locked once the framework is approved (G5)
            'narrative' => 'Narasi yang ditulis analis.',
            'early_indicators' => ['Indikator analis'],
        ])->assertStatus(200)->assertJsonPath('scenario.quadrants.0.edited', true);

        $this->generate($scenario)->assertStatus(409);
        $this->assertSame('Narasi yang ditulis analis.', $scenario->quadrants()->where('position', $first['position'])->first()->narrative);

        $this->generate($scenario, ['overwrite_edited' => true])->assertStatus(202);
        $this->assertSame(0, $scenario->quadrants()->whereNotNull('edited_at')->count());
    }

    public function test_generate_queues_a_job_and_reports_its_status_until_done(): void
    {
        Queue::fake();
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');

        $this->generate($scenario)->assertStatus(202)
            ->assertJsonPath('scenario.generation.status', 'queued')
            ->assertJsonPath('scenario.generation.message', null);
        $this->assertSame([], $llm->calls);
        Queue::assertPushed(GenerateScenarioNarrative::class, fn ($job) => $job->scenario->is($scenario) && $job->overwriteEdited === false);

        // A second request while the first is queued is refused.
        $this->generate($scenario)->assertStatus(409);
        $this->actingAs($this->viewer)->getJson('/scenarios/fetch')->assertJsonPath('0.generation_status', 'queued');

        $llm->respond($this->narrative(['HH' => ['S1'], 'HL' => ['S1'], 'LH' => ['S2'], 'LL' => ['S2']]));
        $job = Queue::pushed(GenerateScenarioNarrative::class)->first();
        app()->call([$job, 'handle']);
        // A duplicate delivery of the same job does nothing.
        app()->call([$job, 'handle']);

        $this->actingAs($this->viewer)->getJson("/scenarios/{$scenario->uuid}")
            ->assertJsonPath('generation.status', 'completed')
            ->assertJsonPath('provider', 'claude')
            ->assertJsonCount(4, 'quadrants');
        $this->assertCount(1, $llm->calls);
    }

    public function test_a_failed_job_records_an_error_and_keeps_the_quadrants(): void
    {
        Queue::fake();
        $scenario = $this->scenario();
        $this->generate($scenario)->assertStatus(202);

        Queue::pushed(GenerateScenarioNarrative::class)->first()->failed(new RuntimeException('Worker killed'));

        $this->actingAs($this->viewer)->getJson("/scenarios/{$scenario->uuid}")
            ->assertJsonPath('generation.status', 'failed')
            ->assertJsonPath('generation.error', 'Narasi tidak dibuat karena kesalahan server. Kuadran yang ada tidak diubah; coba buat ulang.');
        // Failed, not pending: the analyst can start again.
        $this->generate($scenario)->assertStatus(202)->assertJsonPath('scenario.generation.status', 'queued');
    }

    /** G24: the evidence rules follow the client the generation runs on, not the platform default. */
    public function test_evidence_rules_follow_the_client_the_generation_uses(): void
    {
        $this->fakeLlm(false);
        $organization = $this->organizationLlm($this->manager);
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');

        $this->generate($scenario)->assertStatus(422)->assertJsonValidationErrors('evidence');

        $this->assertSame([], $organization->calls);
    }

    /** TENANT-008: the queued job runs as its tenant and uses that tenant's key and quota. */
    public function test_queued_generation_uses_the_organization_key_and_counts_usage(): void
    {
        $platform = $this->fakeLlm();
        $organization = $this->organizationLlm($this->manager);
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $organization->respond($this->narrative(array_fill_keys(Scenario::POSITIONS, ['S1', 'S2'])));

        $this->generate($scenario)->assertStatus(202);

        $this->assertCount(1, $organization->calls);
        $this->assertSame([], $platform->calls);
        $this->assertSame('completed', $scenario->fresh()->generation_status->value);
        $this->assertSame(1, TenantLlmUsage::where('tenant_id', $this->manager->tenant_id)->value('requests'));
    }

    /**
     * Give the user's tenant an AI key of its own, answered by a fake, so no real provider is called.
     */
    private function organizationLlm(User $user): FakeLlmClient
    {
        TenantLlmSetting::create([
            'tenant_id' => $user->tenant_id, 'provider' => 'openai', 'model' => 'org-model',
            'api_key' => 'sk-org-test', 'is_enabled' => true, 'allow_user_byok' => false,
        ]);
        $organization = new FakeLlmClient;
        $resolver = Mockery::mock(LlmClientResolver::class)->makePartial();
        $resolver->shouldReceive('createClientFromTenantSetting')->andReturn($organization);
        $this->app->instance(LlmClientResolver::class, $resolver);

        return $organization;
    }

    /** Roadmap R1.12: a narrative run records provider, model, prompt version and evidence. */
    public function test_an_ai_narrative_is_recorded_as_a_run_that_the_scenario_and_quadrants_link_to(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $s1 = $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $llm->respond($this->narrative(['HH' => ['S1'], 'HL' => ['S1'], 'LH' => ['S2'], 'LL' => ['S2']]));

        $this->generate($scenario)->assertStatus(202);

        $run = AiGenerationRun::sole();
        $this->assertSame(['scenario_narrative', 'claude', 'claude-fake', 'completed', 'scenario-v3'], [$run->purpose, $run->provider, $run->model, $run->status, $run->prompt_version]);
        $this->assertSame($scenario->uuid, $run->input_refs['scenario']);
        $this->assertContains($s1->id, $run->input_refs['signals']);
        $this->assertSame($run->id, $scenario->fresh()->generation_run_id);
        $this->assertSame(['ai'], $scenario->quadrants()->pluck('origin')->unique()->values()->all());
        $this->assertSame([$run->id], $scenario->quadrants()->pluck('generation_run_id')->unique()->values()->all());
    }

    public function test_rejected_and_failed_narrative_calls_are_recorded_without_links(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');

        // Citations outside the offered evidence: the whole result is rejected.
        $llm->respond($this->narrative(['HH' => ['S9']]));
        $this->generate($scenario);
        $rejected = AiGenerationRun::latest('id')->first();
        $this->assertSame('rejected', $rejected->status);
        // An exercise framework keeps its four named quadrants; nothing was written into them.
        $this->assertSame(0, $scenario->quadrants()->whereNotNull('narrative')->count());

        // The provider fails: the template is written and links no run.
        $llm->respond(new LlmException('rate_limited'));
        $this->generate($scenario)->assertStatus(202);
        $failed = AiGenerationRun::latest('id')->first();
        $this->assertSame(['failed', 'rate_limited'], [$failed->status, $failed->error]);
        $this->assertNull($scenario->fresh()->generation_run_id);
        $this->assertSame([null], $scenario->quadrants()->pluck('generation_run_id')->unique()->values()->all());
    }

    /** @param  array<string, array<int, array{0: string, 1: string, 2: array<int, string>}>>  $statements */
    private function typedNarrative(array $statements): array
    {
        return ['quadrants' => array_map(fn ($position) => [
            'position' => $position,
            'title' => "Masa depan {$position}",
            'statements' => array_map(fn ($row) => ['text' => $row[0], 'kind' => $row[1], 'refs' => $row[2]], $statements[$position]),
            'implications' => "Implikasi {$position}.",
            'early_indicators' => ["Indikator {$position}"],
            'cited_refs' => [],
        ], Scenario::POSITIONS)];
    }

    /** Roadmap R2.7: each narrative sentence carries its kind, and every citation becomes a row queryable by signal. */
    public function test_narrative_statements_carry_a_kind_and_become_citation_rows(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $s1 = $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $s2 = $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $other = [['Penjualan EV tumbuh.', 'fact', ['S2']]];
        $llm->respond($this->typedNarrative([
            'HH' => [
                ['Aturan TKDN berlaku.', 'fact', ['S1']],
                ['Permintaan akan naik.', 'analysis', ['S2', 'S9']],
                ['Harga turun drastis.', 'fact', []],
            ],
            'HL' => $other, 'LH' => $other, 'LL' => $other,
        ]));

        $response = $this->generate($scenario)->assertStatus(202);

        $hh = collect($response->json('scenario.quadrants'))->keyBy('position')['HH'];
        $this->assertSame(['S1', 'S2'], $hh['cited_refs']);
        $this->assertSame(['fact', 'analysis', 'interpretation'], array_column($hh['statements'], 'kind'));
        $this->assertSame([['S1'], ['S2'], []], array_column($hh['statements'], 'refs'));
        $this->assertStringContainsString('Aturan TKDN berlaku. [S1]', $hh['narrative']);
        $this->assertSame('scenario-v3', $scenario->fresh()->prompt_version);

        $quadrant = $scenario->quadrants()->where('position', 'HH')->sole();
        $rows = ScenarioCitation::where('scenario_quadrant_id', $quadrant->id)->orderBy('statement_index')->get();
        $this->assertSame([[$s1->id, 'S1', 'fact', 0], [$s2->id, 'S2', 'analysis', 1]], $rows->map(fn ($row) => [$row->signal_id, $row->ref, $row->statement_kind, $row->statement_index])->all());
        $this->assertSame(1, ScenarioCitation::where('signal_id', $s1->id)->count());
        $this->assertSame(4, ScenarioCitation::where('signal_id', $s2->id)->count());
    }

    public function test_editing_a_narrative_drops_statement_kinds_but_keeps_its_citations(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $one = [['Penjualan EV tumbuh.', 'fact', ['S2']]];
        $llm->respond($this->typedNarrative(['HH' => [['Aturan TKDN berlaku.', 'fact', ['S1']]], 'HL' => $one, 'LH' => $one, 'LL' => $one]));
        $this->generate($scenario)->assertStatus(202);
        $quadrant = $scenario->quadrants()->where('position', 'HH')->sole();

        $this->actingAs($this->manager)->putJson("/scenarios/{$scenario->uuid}/quadrants/{$quadrant->uuid}", [
            'title' => $quadrant->title, 'narrative' => 'Narasi disunting analis [S1].', 'implications' => 'Implikasi.', 'early_indicators' => ['Indikator'],
        ])->assertOk();

        $this->assertNull($quadrant->fresh()->narrative_statements);
        $this->assertSame([[null, null]], ScenarioCitation::where('scenario_quadrant_id', $quadrant->id)->get()->map(fn ($row) => [$row->statement_kind, $row->statement_index])->all());
    }

    public function test_the_signal_detail_lists_the_quadrants_that_cite_it(): void
    {
        $llm = $this->fakeLlm();
        $scenario = $this->scenario();
        $s1 = $this->acceptedSignal($this->regulation, 'Local content rule enacted');
        $this->acceptedSignal($this->adoption, 'EV sales doubled');
        $one = [['Penjualan EV tumbuh.', 'fact', ['S2']]];
        $llm->respond($this->typedNarrative(['HH' => [['Aturan TKDN berlaku.', 'fact', ['S1']]], 'HL' => $one, 'LH' => $one, 'LL' => $one]));
        $this->generate($scenario)->assertStatus(202);

        Permission::firstOrCreate(['name' => 'view-signal', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'signal', 'description' => 'view-signal']);
        $this->viewer->givePermissionTo('view-signal');

        $this->actingAs($this->viewer)->getJson(route('signals.citations', $s1))
            ->assertOk()
            ->assertJsonCount(1, 'citations')
            ->assertJsonPath('citations.0.scenario', 'EV 2030')
            ->assertJsonPath('citations.0.position', 'HH')
            ->assertJsonPath('citations.0.kind', 'fact')
            ->assertJsonPath('citations.0.statement', 'Aturan TKDN berlaku.');
    }
}
