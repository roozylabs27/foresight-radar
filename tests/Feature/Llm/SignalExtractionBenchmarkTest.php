<?php

namespace Tests\Feature\Llm;

use App\Enums\LlmJobStatus;
use App\Models\Dimension;
use App\Models\Environment;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Extraction\LlmSignalExtractor;
use App\Services\Llm\LlmException;
use App\Services\SignalExtractionService;
use App\Support\EvidenceVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

class SignalExtractionBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;

    private array $benchmarkData;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-signal', 'create-signal', 'review-signal'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid4(), 'category' => 'signal', 'description' => $name]
            );
        }
        $this->analyst = User::factory()->create();
        $this->analyst->givePermissionTo(['view-signal', 'create-signal', 'review-signal']);

        $macro = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Regulation', 'environment_id' => $macro->id]);
        Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Technology', 'environment_id' => $macro->id]);
        Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Economy', 'environment_id' => $macro->id]);

        TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Short Term', 'code' => 'ST']);
        TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);
        TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Long Term', 'code' => 'LT']);

        config(['services.signal_extraction.driver' => 'claude']);

        $json = file_get_contents(base_path('tests/Fixtures/llm-benchmark/sources.json'));
        $this->benchmarkData = json_decode((string) $json, true);
    }

    public function test_benchmark_verbatim_quote_fidelity(): void
    {
        foreach ($this->benchmarkData as $benchmark) {
            $content = $benchmark['content'];

            foreach ($benchmark['expected_signals'] as $signal) {
                $this->assertTrue(
                    EvidenceVerifier::verify($signal['exact_quote'], $content),
                    "Expected quote for signal '{$signal['title']}' must pass verbatim verification."
                );
            }
        }
    }

    public function test_benchmark_hallucinated_quote_rejection(): void
    {
        foreach ($this->benchmarkData as $benchmark) {
            $content = $benchmark['content'];

            foreach ($benchmark['negative_quotes'] as $hallucinatedQuote) {
                $this->assertFalse(
                    EvidenceVerifier::verify($hallucinatedQuote, $content),
                    "Paraphrased or hallucinated quote '{$hallucinatedQuote}' must be strictly rejected."
                );
            }
        }
    }

    public function test_benchmark_end_to_end_llm_extraction_scoring(): void
    {
        foreach ($this->benchmarkData as $benchmark) {
            $source = Source::create([
                'uuid' => (string) Uuid::uuid4(),
                'title' => $benchmark['title'],
                'raw_content' => $benchmark['content'],
                'content_hash' => hash('sha256', $benchmark['content']),
                'extraction_status' => LlmJobStatus::QUEUED,
                'created_by' => $this->analyst->id,
            ]);

            $candidates = array_map(fn ($s) => [
                'title' => $s['title'],
                'summary' => $s['title'].' identified from policy disclosures.',
                'evidence_quote' => $s['exact_quote'],
                'significance' => 'High strategic impact on industry roadmap.',
                'dimension' => $s['dimension'],
                'time_horizon' => $s['time_horizon'],
            ], $benchmark['expected_signals']);

            $llm = (new FakeLlmClient)
                ->respond([
                    'signals' => $candidates,
                ]);

            $extractor = new LlmSignalExtractor($llm);
            $service = new SignalExtractionService($extractor, $llm);

            $service->extract($source);

            $source->refresh();
            $this->assertSame(LlmJobStatus::COMPLETED, $source->extraction_status);
            $this->assertCount(count($benchmark['expected_signals']), $source->signals);

            foreach ($source->signals as $extractedSignal) {
                $this->assertSame('PENDING', $extractedSignal->review_status);
                $this->assertTrue(
                    EvidenceVerifier::verify($extractedSignal->evidence_quote, $benchmark['content']),
                    'Stored signal evidence quote must match verbatim source text.'
                );
            }
        }
    }

    public function test_benchmark_heuristic_fallback_resilience(): void
    {
        $benchmark = $this->benchmarkData[0];

        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => $benchmark['title'],
            'raw_content' => $benchmark['content'],
            'content_hash' => hash('sha256', $benchmark['content']),
            'extraction_status' => LlmJobStatus::QUEUED,
            'created_by' => $this->analyst->id,
        ]);

        $failingLlm = (new FakeLlmClient)
            ->respond(new LlmException('unavailable', 'Benchmark simulated gateway outage'));

        $extractor = new LlmSignalExtractor($failingLlm);
        $service = new SignalExtractionService($extractor, $failingLlm);

        $service->extract($source);

        $source->refresh();
        $this->assertSame(LlmJobStatus::COMPLETED, $source->extraction_status);
        $this->assertNotEmpty($source->signals);
        $notice = $source->extraction_meta['notice'] ?? null;
        $this->assertNotNull($notice);
        $this->assertStringContainsString('tanpa AI', $notice);

        foreach ($source->signals as $fallbackSignal) {
            $this->assertTrue(
                EvidenceVerifier::verify($fallbackSignal->evidence_quote, $benchmark['content']),
                'Heuristic fallback quotes must also be verbatim excerpts from the source document.'
            );
        }
    }
}
