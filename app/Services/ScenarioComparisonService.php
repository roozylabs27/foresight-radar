<?php

namespace App\Services;

use App\Models\EarlyWarningIndicator;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use Illuminate\Support\Collection;

/**
 * Roadmap R2.8: the four futures of an exercise side by side. Only agreed content is compared:
 * accepted conditions and implications, responses that are not rejected, and active, accepted
 * indicators. Proposals stay on their own step pages.
 */
class ScenarioComparisonService
{
    public function __construct(protected ScenarioConsistencyService $consistency) {}

    /**
     * Columns in matrix order (HH, HL, LH, LL), each with its consistency review (slice 2).
     *
     * @return array<int, array<string, mixed>>
     */
    public function quadrants(Scenario $framework): array
    {
        $checks = $this->consistency->checks($framework);

        return $framework->quadrants()->with(['conditions', 'implicationItems', 'consistencyReviewer:id,name'])->get()
            ->map(function (ScenarioQuadrant $quadrant) use ($framework, $checks) {
                $implications = $quadrant->implicationItems->where('review_status', 'accepted');

                return [
                    'id' => $quadrant->uuid,
                    'position' => $quadrant->position,
                    'poles' => $framework->poles($quadrant->position),
                    'title' => $quadrant->title,
                    'summary' => $quadrant->summary,
                    'review_status' => $quadrant->narrative ? $quadrant->review_status : null,
                    'stale' => $quadrant->stale_since !== null,
                    'conditions' => $quadrant->conditions->where('review_status', 'accepted')
                        ->map(fn (ScenarioCondition $condition) => ['kind' => $condition->kind, 'text' => $condition->text])->values()->all(),
                    'opportunities' => $this->titles($implications, 'opportunity'),
                    'challenges' => $this->titles($implications, 'challenge'),
                    'consistency' => [
                        'status' => $quadrant->consistency_status,
                        'note' => $quadrant->consistency_note,
                        'reviewed_by' => $quadrant->consistencyReviewer?->name,
                        'reviewed_at' => $quadrant->consistency_reviewed_at?->toISOString(),
                        // Reviewed before, but an accepted condition changed since.
                        'needs_review' => $quadrant->consistency_status === null && $quadrant->consistency_reviewed_at !== null,
                        'checks' => $checks[$quadrant->position] ?? [],
                    ],
                ];
            })->values()->all();
    }

    /**
     * Responses that are not rejected, with their fit per quadrant and robustness.
     *
     * @return array<int, array<string, mixed>>
     */
    public function responses(ScenarioPlanningProject $project): array
    {
        return $project->strategicResponses()->where('status', '!=', 'rejected')->with('fits.quadrant')->get()
            ->sortBy([fn ($a, $b) => ($a->priority ?? PHP_INT_MAX) <=> ($b->priority ?? PHP_INT_MAX), fn ($a, $b) => strcmp($a->title, $b->title)])
            ->map(function (StrategicResponse $response) {
                $fit = $response->fits->mapWithKeys(fn ($row) => [$row->quadrant->position => $row->fit]);
                $applies = $fit->filter(fn ($value) => $value === 'applies')->count();

                return [
                    'id' => $response->uuid,
                    'title' => $response->title,
                    'status' => $response->status,
                    'fit' => (object) $fit->all(),
                    'robustness' => $fit->isEmpty() ? null : StrategicResponseService::robustness($applies),
                ];
            })->values()->all();
    }

    /**
     * Active, accepted indicators with the expected direction per quadrant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function indicators(ScenarioPlanningProject $project): array
    {
        return EarlyWarningIndicator::where('project_id', $project->id)
            ->where('status', 'active')->where('review_status', 'accepted')
            ->with('directions.quadrant')->orderBy('name')->get()
            ->map(function (EarlyWarningIndicator $indicator) {
                $directions = $indicator->directions->mapWithKeys(fn ($row) => [$row->quadrant->position => $row->expected_direction]);

                return [
                    'id' => $indicator->uuid,
                    'name' => $indicator->name,
                    'directions' => (object) $directions->all(),
                    'discriminates' => EarlyWarningIndicatorService::discriminates($directions->values()),
                ];
            })->values()->all();
    }

    /**
     * @param  Collection<int, ScenarioImplication>  $items
     * @return array<int, string>
     */
    private function titles($items, string $type): array
    {
        return $items->where('type', $type)->pluck('title')->values()->all();
    }
}
