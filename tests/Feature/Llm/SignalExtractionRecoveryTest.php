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
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
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

    public function test_processing_within_the_job_timeout_plus_grace_is_not_reported_failed(): void
    {
        config(['queue.llm_job_timeout' => 300]);
        $source = $this->createSource(LlmJobStatus::PROCESSING->value, now()->subSeconds(330));

        $this->assertSame('processing', $this->service->progress($source)['status']);
        $this->assertSame(LlmJobStatus::PROCESSING, $source->fresh()->extraction_status);
    }

    public function test_a_queued_source_the_worker_never_picked_up_becomes_failed_and_retryable(): void
    {
        Queue::fake();
        $source = $this->createSource(LlmJobStatus::QUEUED->value, now()->subMinutes(20));

        $progress = $this->service->progress($source);

        $this->assertSame('failed', $progress['status']);
        $this->assertStringContainsString('tidak dimulai', (string) $progress['error']);
        $this->assertSame(LlmJobStatus::FAILED, $source->fresh()->extraction_status);

        $this->service->retry($source->fresh());
        $this->assertSame(LlmJobStatus::QUEUED, $source->fresh()->extraction_status);
        $this->assertNull($source->fresh()->extraction_error);
        Queue::assertPushed(ExtractSignals::class, 1);
    }

    public function test_a_recently_queued_source_stays_queued_and_cannot_be_retried(): void
    {
        Queue::fake();
        $source = $this->createSource(LlmJobStatus::QUEUED->value, now()->subMinute());

        $this->assertSame('queued', $this->service->progress($source)['status']);
        $this->expectException(ValidationException::class);
        try {
            $this->service->retry($source->fresh());
        } finally {
            Queue::assertNothingPushed();
        }
    }

    public function test_a_stale_queued_source_can_be_retried_directly(): void
    {
        Queue::fake();
        $source = $this->createSource(LlmJobStatus::QUEUED->value, now()->subMinutes(20));
        // The lost job's unique lock is still held; the retry must not be swallowed by it.
        (new UniqueLock(app(CacheRepository::class)))->acquire(new ExtractSignals($source));

        $this->service->retry($source);

        $this->assertSame(LlmJobStatus::QUEUED, $source->fresh()->extraction_status);
        Queue::assertPushed(ExtractSignals::class, 1);
    }

    public function test_extraction_runs_only_for_the_worker_that_claims_the_queued_source(): void
    {
        foreach ([LlmJobStatus::PROCESSING, LlmJobStatus::FAILED] as $status) {
            $source = $this->createSource($status->value);
            // A stale in-memory copy still says QUEUED, as a second job would see it.
            $copy = $source->replicate();
            $copy->id = $source->id;
            $copy->exists = true;
            $copy->extraction_status = LlmJobStatus::QUEUED;

            $this->service->extract($copy);

            $this->assertSame($status, $source->fresh()->extraction_status, $status->value);
            $this->assertSame(0, Signal::where('source_id', $source->id)->count(), $status->value);
        }
    }

    public function test_completion_clears_an_error_written_while_the_job_ran(): void
    {
        $source = $this->createSource(LlmJobStatus::QUEUED->value);
        Signal::creating(function () use ($source) {
            Source::whereKey($source->id)->update(['extraction_error' => 'Ekstraksi sinyal terhenti.']);
        });

        $this->service->extract($source);

        $this->assertSame(LlmJobStatus::COMPLETED, $source->fresh()->extraction_status);
        $this->assertNull($source->fresh()->extraction_error);
    }

    public function test_the_failed_handler_does_not_overwrite_a_completed_source(): void
    {
        $source = $this->createSource(LlmJobStatus::COMPLETED->value);

        (new ExtractSignals($source))->failed(new \RuntimeException('late failure'));

        $this->assertSame(LlmJobStatus::COMPLETED, $source->fresh()->extraction_status);
        $this->assertNull($source->fresh()->extraction_error);
    }

    public function test_the_unique_lock_expires_so_a_lost_job_cannot_block_a_retry(): void
    {
        $job = new ExtractSignals($this->createSource(LlmJobStatus::QUEUED->value));

        $this->assertGreaterThan(0, $job->uniqueFor);
    }
}
