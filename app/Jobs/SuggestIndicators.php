<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\ScenarioPlanningProject;
use App\Services\IndicatorSuggestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Roadmap R2.6: AI early warning indicators for one exercise, one run at a time. */
class SuggestIndicators implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function uniqueId(): string
    {
        return 'indicator-suggestions-'.$this->project->id;
    }

    public function __construct(public ScenarioPlanningProject $project, public ?int $userId)
    {
        $this->timeout = (int) config('queue.llm_job_timeout', 300);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext($this->project->tenant_id)];
    }

    public function handle(IndicatorSuggestionService $suggestions): void
    {
        if ($this->project->indicator_suggestion_status !== LlmJobStatus::QUEUED->value) {
            return;
        }
        $this->project->update(['indicator_suggestion_status' => LlmJobStatus::PROCESSING->value]);

        $suggestions->generate($this->project, $this->userId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Indicator suggestion job failed', ['project' => $this->project->uuid, 'error' => $e->getMessage()]);

        app(IndicatorSuggestionService::class)->fail($this->project, 'Usulan indikator tidak dibuat karena kesalahan server. Coba lagi.');
    }
}
