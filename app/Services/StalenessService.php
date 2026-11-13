<?php

namespace App\Services;

use App\Models\DrivingForce;
use App\Models\EarlyWarningIndicator;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\Signal;
use App\Models\StrategicResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Roadmap R1.10 (target workflow §6): upstream changes flag downstream work as stale with a reason.
 * Flags never delete or regenerate anything. Reasons accumulate until the team keeps the work
 * ("acknowledge", who and when) or rewrites it (a regenerated or edited narrative clears its quadrant).
 */
class StalenessService
{
    public const FRAMEWORK_REOPENED = 'Kerangka skenario dibuka kembali (persetujuan G5 ditarik)';

    /**
     * Flag the exercise framework and all its quadrants. With `$downstream` (the default) Steps 7–9
     * are flagged too: implications, strategic responses and indicators (audit U6). A threshold
     * crossing passes false and flags only the responses itself.
     */
    public function markFramework(ScenarioPlanningProject $project, string $reason, bool $downstream = true): void
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            return;
        }

        $framework->forceFill(['stale_since' => $framework->stale_since ?? now(), 'stale_reason' => $this->append($framework->stale_reason, $reason)])->save();
        $this->markQuadrants($framework->quadrants()->get(), $reason);
        if ($downstream) {
            $this->markDownstream($project, $reason);
        }
    }

    /** Audit U6: flag Steps 7–9 of the exercise (implications, strategic responses, indicators). */
    public function markDownstream(ScenarioPlanningProject $project, string $reason): void
    {
        $framework = $project->framework()->first();
        if ($framework) {
            $this->markImplications(ScenarioImplication::whereIn('scenario_quadrant_id', $framework->quadrants()->select('id'))->get(), $reason);
        }
        $this->markResponses($project, $reason);
        foreach ($project->indicators()->get() as $indicator) {
            $this->flag($indicator, $reason);
        }
    }

    /** Audit U6: the approved framework (G5) was withdrawn, so everything built on it needs a look. */
    public function frameworkReopened(ScenarioPlanningProject $project): void
    {
        // PI-03: narratives (G6) and responses (G7) answer the approved framework, so their approvals
        // fall with it. Row by row, so the decision log records each withdrawal.
        $framework = $project->framework()->first();
        if ($framework) {
            foreach ($framework->quadrants()->where('review_status', 'approved')->get() as $quadrant) {
                $quadrant->update(['review_status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null]);
            }
            $this->markQuadrants($framework->quadrants()->get(), self::FRAMEWORK_REOPENED, onlyWithNarrative: true);
        }
        foreach ($project->strategicResponses()->where('status', 'approved')->get() as $response) {
            $response->update(['status' => 'proposed', 'approved_by' => null, 'approved_at' => null]);
        }
        $this->markDownstream($project, self::FRAMEWORK_REOPENED);
    }

    /** @param  Collection<int, ScenarioImplication>  $implications */
    public function markImplications(Collection $implications, string $reason): void
    {
        foreach ($implications as $implication) {
            $this->flag($implication, $reason);
        }
    }

    /** Audit U6: an implication changed or is being removed; flag the responses that rely on it. */
    public function implicationChanged(ScenarioImplication $implication, string $reason): void
    {
        foreach (StrategicResponse::whereHas('implications', fn ($q) => $q->whereKey($implication->id))->get() as $response) {
            $this->flag($response, $reason);
        }
    }

    /** "Keep" for one implication or indicator: an edit by the team settles its flag. */
    public function clear(ScenarioImplication|EarlyWarningIndicator $row): void
    {
        if ($row->getAttribute('stale_since') !== null) {
            $row->forceFill(['stale_since' => null, 'stale_reason' => null])->save();
        }
    }

    private function flag(Model $row, string $reason): void
    {
        $row->forceFill(['stale_since' => $row->getAttribute('stale_since') ?? now(), 'stale_reason' => $this->append($row->getAttribute('stale_reason'), $reason)])->save();
    }

    /**
     * PI-06: a firm library force changed while exercises use it. Each exercise link is flagged with
     * the reason, and a framework whose axis rests on the force is flagged too.
     */
    public function firmForceChanged(DrivingForce $force, string $reason): void
    {
        $links = ProjectDrivingForce::where('driving_force_id', $force->id)->with('project')->get();
        foreach ($links as $link) {
            $this->flag($link, $reason);
            if ($link->project) {
                $this->markIfAxisForce($link->project, $link->id, $reason);
            }
        }
    }

    /** PI-06: saving the exercise force is the team's review of a library change. */
    public function clearProjectForce(ProjectDrivingForce $link): void
    {
        if ($link->stale_since !== null) {
            $link->forceFill(['stale_since' => null, 'stale_reason' => null])->save();
        }
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

    /** Audit U4: flag every strategic response of the exercise (a threshold crossing may change what to do). */
    public function markResponses(ScenarioPlanningProject $project, string $reason): void
    {
        foreach ($project->strategicResponses()->get() as $response) {
            $this->flag($response, $reason);
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
        // Audit U6: implications and indicators are kept with the framework; responses keep their own "keep".
        ScenarioImplication::whereIn('scenario_quadrant_id', $framework->quadrants()->select('id'))->update(['stale_since' => null, 'stale_reason' => null]);
        $project->indicators()->update(['stale_since' => null, 'stale_reason' => null]);
    }

    /**
     * Stale state for the exercise overview, or null when nothing is flagged.
     *
     * `downstream` counts the flagged Steps 7–9 items (audit U6).
     *
     * @return array{since: ?string, reasons: array<int, string>, quadrants: array<int, array{position: string, title: string, reason: ?string}>, downstream: array{implications: int, responses: int, indicators: int}}|null
     */
    public function present(ScenarioPlanningProject $project): ?array
    {
        $framework = $project->framework()->first();
        if (! $framework) {
            return null;
        }
        $quadrants = $framework->quadrants()->whereNotNull('stale_since')->get();
        $implications = ScenarioImplication::whereIn('scenario_quadrant_id', $framework->quadrants()->select('id'))->whereNotNull('stale_since')->get(['stale_since', 'stale_reason']);
        $indicators = $project->indicators()->whereNotNull('stale_since')->get(['stale_since', 'stale_reason']);
        $responses = $project->strategicResponses()->whereNotNull('stale_since')->count();
        if ($framework->stale_since === null && $quadrants->isEmpty() && $implications->isEmpty() && $indicators->isEmpty() && $responses === 0) {
            return null;
        }

        $since = $framework->stale_since ?? $quadrants->min('stale_since') ?? $implications->min('stale_since') ?? $indicators->min('stale_since');
        $reasons = collect(explode('; ', (string) $framework->stale_reason))
            ->merge($quadrants->flatMap(fn ($quadrant) => explode('; ', (string) $quadrant->stale_reason)))
            ->merge($implications->toBase()->concat($indicators)->flatMap(fn ($row) => explode('; ', (string) $row->getAttribute('stale_reason'))))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'since' => $since?->toISOString(),
            'reasons' => $reasons,
            'quadrants' => $quadrants->map(fn ($quadrant) => ['position' => $quadrant->position, 'title' => $quadrant->title, 'reason' => $quadrant->stale_reason])->values()->all(),
            'downstream' => ['implications' => $implications->count(), 'responses' => $responses, 'indicators' => $indicators->count()],
        ];
    }

    private function append(?string $reasons, string $reason): string
    {
        $list = collect(explode('; ', (string) $reasons))->filter()->push($reason)->unique()->values();

        return Str::limit($list->implode('; '), 500, '');
    }
}
