<?php

namespace App\Services;

use App\Models\ScenarioCondition;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Signal;
use App\Models\StrategicResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Roadmap R1.10 (target workflow §6): upstream changes flag downstream work as stale with a reason.
 * Flags never delete or regenerate anything. Reasons accumulate until the team keeps the work
 * ("acknowledge", who and when) or rewrites it (a regenerated or edited narrative clears its quadrant).
 */
class StalenessService
{
    /** Flag the exercise framework and all its quadrants. */
    public function markFramework(ScenarioPlanningProject $project, string $reason): void
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            return;
        }

        $framework->forceFill(['stale_since' => $framework->stale_since ?? now(), 'stale_reason' => $this->append($framework->stale_reason, $reason)])->save();
        $this->markQuadrants($framework->quadrants()->get(), $reason);
    }

    /** Flag a force's framework when the force belongs to one of its axes. */
    public function markIfAxisForce(ScenarioPlanningProject $project, int $projectForceId, string $reason): void
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            return;
        }
        $isAxis = $framework->axis_a_uncertainty?->projectForces()->whereKey($projectForceId)->exists()
            || $framework->axis_b_uncertainty?->projectForces()->whereKey($projectForceId)->exists();
        if ($isAxis) {
            $this->markFramework($project, $reason);
        }
    }

    /**
     * Flag the given quadrants that already have a narrative (work that may now be outdated).
     *
     * @param  Collection<int, ScenarioQuadrant>  $quadrants
     */
    public function markQuadrants(Collection $quadrants, string $reason, bool $onlyWithNarrative = false): void
    {
        foreach ($quadrants as $quadrant) {
            if ($onlyWithNarrative && blank($quadrant->narrative)) {
                continue;
            }
            $quadrant->forceFill(['stale_since' => $quadrant->stale_since ?? now(), 'stale_reason' => $this->append($quadrant->stale_reason, $reason)])->save();
        }
    }

    /** A signal stopped being evidence (rejected or deleted): flag every quadrant that cites it. */
    public function signalWithdrawn(Signal $signal): void
    {
        // Citation rows (roadmap R2.7) answer "who cites this signal" without scanning JSON.
        $quadrants = ScenarioQuadrant::whereHas('citations', fn ($q) => $q->where('signal_id', $signal->id))->get();

        $this->markQuadrants($quadrants, 'Sinyal yang dikutip ditolak atau dihapus: '.Str::limit($signal->title, 80));
    }

    /** Roadmap R2.9: flag the responses that rely on a condition that changed or is being removed. */
    public function conditionChanged(ScenarioCondition $condition, string $reason): void
    {
        foreach ($condition->responses()->get() as $response) {
            $response->forceFill(['stale_since' => $response->stale_since ?? now(), 'stale_reason' => $this->append($response->stale_reason, $reason)])->save();
        }
    }

    /** "Keep" for one response: the team checked it against the changed conditions. */
    public function clearResponse(StrategicResponse $response): void
    {
        if ($response->stale_since !== null) {
            $response->forceFill(['stale_since' => null, 'stale_reason' => null])->save();
        }
    }

    public function clearQuadrant(ScenarioQuadrant $quadrant): void
    {
        if ($quadrant->stale_since !== null) {
            $quadrant->forceFill(['stale_since' => null, 'stale_reason' => null])->save();
        }
    }

    /** "Keep": the team reviewed the flagged work and keeps it as it is. */
    public function acknowledge(ScenarioPlanningProject $project, User $user): void
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            return;
        }

        $framework->forceFill(['stale_since' => null, 'stale_reason' => null, 'stale_acknowledged_by' => $user->id, 'stale_acknowledged_at' => now()])->save();
        $framework->quadrants()->update(['stale_since' => null, 'stale_reason' => null]);
    }

    /**
     * Stale state for the exercise overview, or null when nothing is flagged.
     *
     * @return array{since: string, reasons: array<int, string>, quadrants: array<int, array{position: string, title: string, reason: ?string}>}|null
     */
    public function present(ScenarioPlanningProject $project): ?array
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            return null;
        }
        $quadrants = $framework->quadrants()->whereNotNull('stale_since')->get();
        if ($framework->stale_since === null && $quadrants->isEmpty()) {
            return null;
        }

        $since = $framework->stale_since ?? $quadrants->min('stale_since');
        $reasons = collect(explode('; ', (string) $framework->stale_reason))
            ->merge($quadrants->flatMap(fn ($quadrant) => explode('; ', (string) $quadrant->stale_reason)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'since' => $since?->toISOString(),
            'reasons' => $reasons,
            'quadrants' => $quadrants->map(fn ($quadrant) => ['position' => $quadrant->position, 'title' => $quadrant->title, 'reason' => $quadrant->stale_reason])->values()->all(),
        ];
    }

    private function append(?string $reasons, string $reason): string
    {
        $list = collect(explode('; ', (string) $reasons))->filter()->push($reason)->unique()->values();

        return Str::limit($list->implode('; '), 500, '');
    }
}
