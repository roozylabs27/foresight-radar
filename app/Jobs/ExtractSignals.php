<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\Source;
use App\Services\SignalExtractionService;
use App\Support\StaleJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Extracts candidate signals from an ingested source. Runs once: an LLM
 * failure already marks the source failed inside the service (the analyst
 * retries it from the Signals page), so a failure here is a server error, and
 * an automatic retry would repeat a paid call.
 */
class ExtractSignals implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    /** The unique lock expires, so a job lost from the queue cannot block a retry forever. */
    public int $uniqueFor;

    public function uniqueId(): string
    {
        return (string) $this->source->id;
    }

    public function __construct(public Source $source)
    {
        $this->timeout = (int) config('queue.llm_job_timeout', 300);
        $this->uniqueFor = StaleJob::lockSeconds($this->timeout);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext($this->source->tenant_id)];
    }

    public function handle(SignalExtractionService $extraction): void
    {
        // The service claims the source atomically: a duplicate job, or one for a source that
        // already failed or completed, does nothing.
        try {
            $extraction->extract($this->source);
        } catch (Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    /**
     * Runs outside the tenant context, so it changes only this source, by key, and only while its
     * extraction is still pending: a late failure never overwrites a completed or failed result.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Signal extraction job failed', ['source' => $this->source->uuid, 'error' => $e->getMessage()]);

        Source::withoutGlobalScopes()->whereKey($this->source->id)
            ->whereIn('extraction_status', [LlmJobStatus::QUEUED->value, LlmJobStatus::PROCESSING->value])
            ->update([
                'extraction_status' => LlmJobStatus::FAILED->value,
                'extraction_error' => 'Ekstraksi sinyal gagal karena kesalahan server. Sumber tersimpan tanpa sinyal; coba lagi dari halaman Sinyal.',
                'updated_at' => now(),
            ]);
    }
}
