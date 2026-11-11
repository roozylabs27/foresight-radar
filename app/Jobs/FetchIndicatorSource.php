<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\EarlyWarningIndicator;
use App\Services\ExternalIndicatorService;
use App\Support\StaleJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Roadmap R3.6: fetch one external indicator's series, one run per indicator at a time. */
class FetchIndicatorSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TIMEOUT = 60;

    public int $tries = 1;

    public int $timeout = self::TIMEOUT;

    /** The unique lock expires, so a job lost from the queue cannot block the next fetch forever. */
    public int $uniqueFor;

    public function uniqueId(): string
    {
        return 'indicator-fetch-'.$this->indicator->id;
    }

    public function __construct(public EarlyWarningIndicator $indicator)
    {
        $this->uniqueFor = StaleJob::lockSeconds(self::TIMEOUT);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext($this->indicator->getAttribute('tenant_id'))];
    }

    public function handle(ExternalIndicatorService $sources): void
    {
        // Atomic: a duplicate job, or one for a fetch already finished or marked lost, does nothing.
        if (! $sources->claim($this->indicator)) {
            return;
        }

        $sources->fetch($this->indicator);
    }

    /** Runs outside the tenant context: changes only this indicator, by key, while its fetch is pending. */
    public function failed(Throwable $e): void
    {
        Log::error('External indicator fetch failed', ['indicator' => $this->indicator->uuid, 'error' => $e->getMessage()]);

        EarlyWarningIndicator::withoutGlobalScopes()->whereKey($this->indicator->id)
            ->whereIn('fetch_status', [LlmJobStatus::QUEUED->value, LlmJobStatus::PROCESSING->value])
            ->update(['fetch_status' => LlmJobStatus::FAILED->value, 'fetch_error' => 'Data tidak diambil karena kesalahan server. Coba lagi.', 'updated_at' => now()]);
    }
}
