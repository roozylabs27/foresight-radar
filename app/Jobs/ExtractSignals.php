<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Models\Source;
use App\Services\SignalExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Extracts candidate signals from an ingested source. Runs once: an LLM
 * failure already falls back to keyword rules inside the service, so a
 * failure here is a server error, and a retry would repeat a paid call.
 */
class ExtractSignals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public Source $source)
    {
        $this->timeout = (int) config('queue.llm_job_timeout', 300);
    }

    public function handle(SignalExtractionService $extraction): void
    {
        if ($this->source->extraction_status === LlmJobStatus::COMPLETED) {
            return;
        }

        $staleWindow = (int) config('queue.llm_job_timeout', 300);
        $isStale = $this->source->extraction_status === LlmJobStatus::PROCESSING
            && $this->source->updated_at
            && $this->source->updated_at->diffInSeconds(now()) >= $staleWindow;

        if ($this->source->extraction_status !== LlmJobStatus::QUEUED
            && $this->source->extraction_status !== LlmJobStatus::FAILED
            && ! $isStale) {
            return;
        }

        try {
            $extraction->extract($this->source);
        } catch (Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Signal extraction job failed', ['source' => $this->source->uuid, 'error' => $e->getMessage()]);

        $this->source->update([
            'extraction_status' => LlmJobStatus::FAILED,
            'extraction_error' => 'Ekstraksi sinyal gagal karena kesalahan server. Sumber tersimpan tanpa sinyal; coba ingest ulang dokumen ini.',
        ]);
    }
}
