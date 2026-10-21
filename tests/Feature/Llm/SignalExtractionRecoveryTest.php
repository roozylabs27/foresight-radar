<?php

namespace Tests\Feature\Llm;

use App\Enums\LlmJobStatus;
use App\Jobs\ExtractSignals;
use App\Models\Dimension;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\SignalExtractionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class SignalExtractionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private SignalExtractionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $env = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        Dimension::firstOrCreate(['name' => 'Technology'], ['uuid' => (string) Uuid::uuid4(), 'environment_id' => $env->id]);
        TimeHorizon::firstOrCreate(['name' => 'Mid Term'], ['uuid' => (string) Uuid::uuid4(), 'code' => 'MT']);

        config(['services.signal_extraction.driver' => 'heuristic']);
        $this->service = app(SignalExtractionService::class);
    }

    private function createSource(string $status = 'QUEUED', ?Carbon $updatedAt = null): Source
    {
        $content = "Artificial intelligence and digital transformation are rapidly accelerating in enterprise software.\n\n".
            "Cloud migration and cybersecurity regulations are shaping technology adoption in 2026.\n\n".
            'Quantum compute algorithms demonstrate breakthroughs in financial modeling and cryptography.';

        $source = Source::create([
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'Tech Disruption Report 2026',
            'raw_content' => $content,
            'content_hash' => hash('sha256', $content),
            'created_by' => $this->user->id,
            'extraction_status' => $status,
        ]);

        if ($updatedAt) {
            $source->updated_at = $updatedAt;
            $source->saveQuietly();
        }

        return $source;
    }

    public function test_failed_job_marks_source_as_failed_with_descriptive_error(): void
    {
        $source = $this->createSource(LlmJobStatus::QUEUED->value);
        $job = new ExtractSignals($source);

        $job->failed(new \RuntimeException('Connection timed out to LLM gateway'));

        $source->refresh();
        $this->assertEquals(LlmJobStatus::FAILED, $source->extraction_status);
        $this->assertNotNull($source->extraction_error);
        $this->assertStringContainsString('Ekstraksi sinyal gagal', $source->extraction_error);
    }

    public function test_repeated_extraction_is_idempotent_and_cleans_pending_signals(): void
    {
        $source = $this->createSource(LlmJobStatus::QUEUED->value);

        // First extraction
        $this->service->extract($source);
        $source->refresh();
        $this->assertEquals(LlmJobStatus::COMPLETED, $source->extraction_status);
        $firstCount = Signal::where('source_id', $source->id)->count();
        $this->assertGreaterThan(0, $firstCount);

        // Re-running extraction (simulate retry/recovery)
        // Reset status to QUEUED to simulate retry
        $source->update(['extraction_status' => LlmJobStatus::QUEUED]);
        $this->service->extract($source);
        $source->refresh();

        $secondCount = Signal::where('source_id', $source->id)->count();
        $this->assertEquals($firstCount, $secondCount, 'Retrying extraction must be idempotent and not create duplicate signals');
    }

    public function test_completed_source_is_not_reprocessed(): void
    {
        $source = $this->createSource(LlmJobStatus::QUEUED->value);
        $this->service->extract($source);
        $source->refresh();
        $originalUpdatedAt = $source->updated_at;

        // Try extracting already completed source
        $this->service->extract($source);

        $this->assertEquals(LlmJobStatus::COMPLETED, $source->fresh()->extraction_status);
    }

    public function test_stale_processing_source_is_reported_as_failed(): void
    {
        $tenMinutesAgo = now()->subMinutes(10);
        $source = $this->createSource(LlmJobStatus::PROCESSING->value, $tenMinutesAgo);

        $progress = $this->service->progress($source);

        $this->assertEquals('failed', $progress['status'], 'Stale job in PROCESSING must report failed to the analyst');
        $this->assertNotNull($progress['error']);
        $this->assertStringContainsString('terhenti', $progress['error']);
    }
}
