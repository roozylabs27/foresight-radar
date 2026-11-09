<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Jobs\Middleware\RunInTenantContext;
use App\Models\ScenarioPlanningProject;
use App\Services\SignalMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Roadmap R2.6: AI signal-to-indicator matching for one exercise, one run at a time. */
class MatchSignals implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function uniqueId(): string
    {
        return 'signal-match-'.$this->project->id;
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

    public function handle(SignalMatchService $matches): void
    {
        if ($this->project->match_suggestion_status !== LlmJobStatus::QUEUED->value) {
            return;
        }
        $this->project->update(['match_suggestion_status' => LlmJobStatus::PROCESSING->value]);

        $matches->generate($this->project, $this->userId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Signal match job failed', ['project' => $this->project->uuid, 'error' => $e->getMessage()]);

        app(SignalMatchService::class)->fail($this->project, 'Pencocokan sinyal tidak dibuat karena kesalahan server. Coba lagi.');
    }
}
