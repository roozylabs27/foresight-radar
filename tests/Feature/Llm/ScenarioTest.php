<?php

namespace Tests\Feature\Llm;

use App\Jobs\GenerateScenarioNarrative;
use App\Models\CrossImpact;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Scenario;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Llm\LlmException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Spatie\Permission\Models\Permission;
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

    private function scenario(): Scenario
    {
        return Scenario::create([
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
            'narrative' => "Narasi {$position} " . implode(' ', array_map(fn ($ref) => "[{$ref}]", $citations[$position] ?? [])),
            'implications' => "Implikasi {$position}.",
            'early_indicators' => ["Indikator {$position}"],
            'cited_refs' => $citations[$position] ?? [],
        ], Scenario::POSITIONS)];
    }

    private function generate(Scenario $scenario, array $body = [])
    {
        return $this->actingAs($this->manager)->postJson("/scenarios/{$scenario->uuid}/generate", $body);
    }

    public function test_viewer_can_read_but_not_create_and_others_are_forbidden(): void
    {
        $scenario = $this->scenario();

        $this->actingAs($this->viewer)->get('/scenarios')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Report/Scenarios')->where('canManage', false)->where('llm.available', false));
        $this->actingAs($this->viewer)->getJson("/scenarios/{$scenario->uuid}")->assertStatus(200)->assertJsonPath('name', 'EV 2030');
        $this->actingAs($this->viewer)->postJson("/scenarios/{$scenario->uuid}/generate")->assertStatus(403);
        $this->actingAs($this->viewer)->postJson('/scenarios', [])->assertStatus(403);
        $this->actingAs(User::factory()->create())->getJson('/scenarios/fetch')->assertStatus(403);
    }

    public function test_create_requires_two_different_approved_forces(): void
    {
        $pending = $this->force('Draft Force', 'Economy', 5, 5, 'PENDING');
        $payload = [
            'name' => 'EV 2030',
            'axis_a' => $this->regulation->uuid,
            'axis_b' => $this->adoption->uuid,
            'axis_a_low_label' => 'Longgar', 'axis_a_high_label' => 'Ketat',
            'axis_b_low_label' => 'Lambat', 'axis_b_high_label' => 'Cepat',
        ];

        $this->actingAs($this->manager)->postJson('/scenarios', $payload)
            ->assertStatus(201)
            ->assertJsonPath('scenario.axis_a.keyword', 'TKDN Regulation')
            ->assertJsonPath('scenario.quadrants', []);
        $this->actingAs($this->manager)->postJson('/scenarios', ['axis_b' => $this->regulation->uuid] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors('axis_b');
        $this->actingAs($this->manager)->postJson('/scenarios', ['axis_b' => $pending->uuid] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors('axis_b');
    }

    public function test_suggested_axes_are_strong_independent_and_from_different_dimensions(): void
    {
        $sameDimension = $this->force('Import Regulation', 'Regulation', 9, 9);
        $coupled = $this->force('Battery Prices', 'Economy', 9, 9);
        $this->force('Minor Trend', 'Ecology', 2, 2);
        CrossImpact::create(['source_driving_force_id' => $coupled->id, 'target_driving_force_id' => $this->adoption->id, 'score' => 3]);

        $response = $this->actingAs($this->viewer)->getJson('/scenarios/suggest-axes')->assertStatus(200);

        $pairs = collect($response->json('pairs'))->map(fn ($pair) => [$pair['a']['keyword'], $pair['b']['keyword']]);
        $this->assertNotContains(['Import Regulation', 'TKDN Regulation'], $pairs);
        $this->assertFalse($pairs->contains(fn ($pair) => in_array('Battery Prices', $pair) && in_array('EV Adoption', $pair)));
        $this->assertTrue($pairs->contains(fn ($pair) => in_array('TKDN Regulation', $pair) && in_array('EV Adoption', $pair)));
        // Highest impact x uncertainty first: Import Regulation and Battery Prices (81) lead the candidates.
        $this->assertSame(['Battery Prices', 'Import Regulation'], collect($response->json('candidates'))->take(2)->pluck('keyword')->sort()->values()->all());
        $this->assertContains(['source' => $coupled->uuid, 'target' => $this->adoption->uuid, 'score' => 3], $response->json('cross_impacts'));
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
        $this->assertSame('scenario-v1', $stored->prompt_version);
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
        $this->assertSame(0, $scenario->quadrants()->count());
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

        $this->assertSame(0, $scenario->quadrants()->count());
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
            'title' => 'Judul analis',
            'narrative' => 'Narasi yang ditulis analis.',
            'early_indicators' => ['Indikator analis'],
        ])->assertStatus(200)->assertJsonPath('scenario.quadrants.0.edited', true);

        $this->generate($scenario)->assertStatus(409);
        $this->assertSame('Judul analis', $scenario->quadrants()->where('position', $first['position'])->first()->title);

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
}
