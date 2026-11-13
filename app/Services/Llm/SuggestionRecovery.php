<?php

namespace App\Services\Llm;

use App\Enums\LlmJobStatus;
use App\Jobs\MatchSignals;
use App\Jobs\SuggestConditions;
use App\Jobs\SuggestFocalIssue;
use App\Jobs\SuggestForceCandidates;
use App\Jobs\SuggestForceScores;
use App\Jobs\SuggestFramework;
use App\Jobs\SuggestImplications;
use App\Jobs\SuggestIndicators;
use App\Jobs\SuggestResponses;
use App\Models\ScenarioPlanningProject;
use App\Support\StaleJob;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Product integrity PI-10: the nine exercise AI assists keep their state in
 * `{kind}_suggestion_status`. A job lost with its worker left that status "queued" for good: the page
 * kept its button disabled and the job's unique lock silently swallowed every new request. The same
 * defect was fixed for signal extraction in audit U2. Past StaleJob's window an assist is marked failed
 * with a reason and its unique lock is released, so the user can simply ask again.
 */
class SuggestionRecovery
{
    /** Status-column prefix => job class (each takes the project and a user id). */
    public const KINDS = [
        'focal' => SuggestFocalIssue::class,
        'force' => SuggestForceCandidates::class,
        'score' => SuggestForceScores::class,
        'framework' => SuggestFramework::class,
        'condition' => SuggestConditions::class,
        'implication' => SuggestImplications::class,
        'response' => SuggestResponses::class,
        'indicator' => SuggestIndicators::class,
        'match' => MatchSignals::class,
    ];

    public const MESSAGE = 'Proses AI terhenti tanpa hasil (pekerja antrean berhenti). Coba lagi.';

    /** @return array<int, string> the kinds that were recovered */
    public function recover(ScenarioPlanningProject $project): array
    {
        $timeout = (int) config('queue.llm_job_timeout', 300);
        $startedAt = (array) ($project->suggestion_started_at ?? []);
        $recovered = [];

        foreach (self::KINDS as $kind => $job) {
            $column = "{$kind}_suggestion_status";
            $status = LlmJobStatus::tryFrom((string) $project->getAttribute($column));
            if (! in_array($status, [LlmJobStatus::QUEUED, LlmJobStatus::PROCESSING], true)) {
                continue;
            }
            $since = isset($startedAt[$kind]) ? Carbon::parse($startedAt[$kind]) : null;
            if (! StaleJob::isStale($status, $since, $timeout)) {
                continue;
            }

            // Conditional update: a worker that finishes at this moment wins, and so does another page load.
            $claimed = ScenarioPlanningProject::whereKey($project->getKey())
                ->where($column, $status->value)
                ->update([$column => LlmJobStatus::FAILED->value, "{$kind}_suggestion_error" => self::MESSAGE]);
            if ($claimed === 0) {
                continue;
            }

            (new UniqueLock(app(CacheRepository::class)))->release(new $job($project, null));
            $project->setRawAttributes(array_merge($project->getAttributes(), [
                $column => LlmJobStatus::FAILED->value,
                "{$kind}_suggestion_error" => self::MESSAGE,
            ]), true);
            $recovered[] = $kind;
        }

        return $recovered;
    }
}
