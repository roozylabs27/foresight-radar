<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\ScenarioPlanningProject;
use App\Services\FocalIssueSuggestionService;
use App\Support\StaleJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Roadmap R3.1: AI critique and alternatives for one exercise's focal question, one run at a time. */
class SuggestFocalIssue implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    /** PI-10: the unique lock expires, so a job lost with its worker cannot block a new request forever. */
    public int $uniqueFor;

    public function uniqueId(): string
    {
        return 'focal-suggestions-'.$this->project->id;
    }

    public function __construct(public ScenarioPlanningProject $project, public ?int $userId)
    {
        $this->timeout = (int) config('queue.llm_job_timeout', 300);
        $this->uniqueFor = StaleJob::lockSeconds($this->timeout);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext($this->project->tenant_id)];
    }

    public function handle(FocalIssueSuggestionService $suggestions): void
    {
        if ($this->project->focal_suggestion_status !== LlmJobStatus::QUEUED->value) {
            return;
        }
        $this->project->update(['focal_suggestion_status' => LlmJobStatus::PROCESSING->value]);

        $suggestions->generate($this->project, $this->userId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Focal issue suggestion job failed', ['project' => $this->project->uuid, 'error' => $e->getMessage()]);

        app(FocalIssueSuggestionService::class)->fail($this->project, 'Saran focal issue tidak dibuat karena kesalahan server. Coba lagi.');
    }
}
