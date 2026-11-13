<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\ScenarioPlanningProject;
use App\Services\ForceCandidateService;
use App\Support\StaleJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Roadmap R2.4: AI candidate forces for one exercise, one run at a time. */
class SuggestForceCandidates implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    /** PI-10: the unique lock expires, so a job lost with its worker cannot block a new request forever. */
    public int $uniqueFor;

    public function uniqueId(): string
    {
        return 'force-candidates-'.$this->project->id;
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

    public function handle(ForceCandidateService $candidates): void
    {
        if ($this->project->force_suggestion_status !== LlmJobStatus::QUEUED->value) {
            return;
        }
        $this->project->update(['force_suggestion_status' => LlmJobStatus::PROCESSING->value]);

        $candidates->generate($this->project, $this->userId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Force candidate job failed', ['project' => $this->project->uuid, 'error' => $e->getMessage()]);

        app(ForceCandidateService::class)->fail($this->project, 'Usulan driving force tidak dibuat karena kesalahan server. Coba lagi.');
    }
}
