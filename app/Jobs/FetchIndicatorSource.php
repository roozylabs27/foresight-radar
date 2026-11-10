<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\EarlyWarningIndicator;
use App\Services\ExternalIndicatorService;
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

    public int $tries = 1;

    public int $timeout = 60;

    public function uniqueId(): string
    {
        return 'indicator-fetch-'.$this->indicator->id;
    }

    public function __construct(public EarlyWarningIndicator $indicator) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext($this->indicator->getAttribute('tenant_id'))];
    }

    public function handle(ExternalIndicatorService $sources): void
    {
        if ($this->indicator->fetch_status !== LlmJobStatus::QUEUED->value) {
            return;
        }
        $this->indicator->forceFill(['fetch_status' => LlmJobStatus::PROCESSING->value])->save();

        $sources->fetch($this->indicator);
    }

    public function failed(Throwable $e): void
    {
        Log::error('External indicator fetch failed', ['indicator' => $this->indicator->uuid, 'error' => $e->getMessage()]);

        app(ExternalIndicatorService::class)->fail($this->indicator, 'Data tidak diambil karena kesalahan server. Coba lagi.');
    }
}
