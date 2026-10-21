<?php

namespace App\Jobs;

use App\Enums\LlmJobStatus;
use App\Models\Scenario;
use App\Services\ScenarioNarrativeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Writes the four quadrants of a scenario. A rejected result (invalid
 * citations, quadrants edited meanwhile, evidence removed) is stored as the
 * generation error for the analyst to read, and nothing is saved.
 */
class GenerateScenarioNarrative implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function uniqueId(): string
    {
        return (string) $this->scenario->id;
    }

    public function __construct(public Scenario $scenario, public bool $overwriteEdited, public ?int $userId)
    {
        $this->timeout = (int) config('queue.llm_job_timeout', 300);
    }

    public function handle(ScenarioNarrativeService $narratives): void
    {
        if ($this->scenario->generation_status !== LlmJobStatus::QUEUED) {
            return;
        }

        $this->scenario->update(['generation_status' => LlmJobStatus::PROCESSING]);

        try {
            $narratives->generate($this->scenario, $this->overwriteEdited, $this->userId);
        } catch (ConflictHttpException $e) {
            $this->reject($e->getMessage());
        } catch (ValidationException $e) {
            $this->reject(collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Scenario narrative job failed', ['scenario' => $this->scenario->uuid, 'error' => $e->getMessage()]);

        $this->reject('Narasi tidak dibuat karena kesalahan server. Kuadran yang ada tidak diubah; coba buat ulang.');
    }

    protected function reject(string $message): void
    {
        $this->scenario->update(['generation_status' => LlmJobStatus::FAILED, 'generation_error' => $message]);
    }
}
