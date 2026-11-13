<?php

namespace App\Services;

use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\Signal;
use Illuminate\Support\Collection;

/**
 * Scenario evidence: which accepted signals support a scenario. Axis suggestions and the creation
 * of stand-alone scenarios were removed with PAD-2; exercises take their axes from critical uncertainties.
 */
class ScenarioService
{
    /** Axes should be independent: neither may push the other this hard (|score|). */
    public const MAX_AXIS_INFLUENCE = 2;

    public const EVIDENCE_PER_AXIS = 30;

    /**
     * Accepted signals linked to either axis (its force, or the member forces of its critical
     * uncertainty), labelled S1..Sn in a fixed
     * order (axis A first, then B, then condition forces; newest first). A
     * signal linked to both axes is listed once, as "AB"; condition-force
     * signals are labelled "C".
     *
     * @return array<int, array{ref: string, axis: string, signal: Signal}>
     */
    public function evidenceFor(Scenario $scenario): array
    {
        $axisForces = [...$scenario->axisForceIds('A'), ...$scenario->axisForceIds('B')];
        $conditionForces = array_values(array_diff($this->conditionForceIds($scenario), $axisForces));
        $byAxis = [
            'A' => $this->acceptedSignals($scenario->axisForceIds('A')),
            'B' => $this->acceptedSignals($scenario->axisForceIds('B')),
            // Signals of non-axis forces named by step 5 conditions (roadmap R1.6), labelled "C".
            'C' => $conditionForces === [] ? collect() : $this->acceptedSignals($conditionForces),
        ];

        $evidence = [];
        $seen = [];
        foreach ($byAxis as $axis => $signals) {
            foreach ($signals as $signal) {
                if (isset($seen[$signal->id])) {
                    if ($axis === 'B') {
                        $evidence[$seen[$signal->id]]['axis'] = 'AB';
                    }

                    continue;
                }
                $seen[$signal->id] = count($evidence);
                $evidence[] = ['ref' => 'S'.(count($evidence) + 1), 'axis' => $axis, 'signal' => $signal];
            }
        }

        return $evidence;
    }

    /**
     * Driving forces described by accepted force-behaviour conditions of a framework.
     *
     * @return array<int, int>
     */
    public function conditionForceIds(Scenario $scenario): array
    {
        if (! $scenario->isFramework()) {
            return [];
        }

        return ScenarioCondition::query()
            ->join('project_driving_forces', 'project_driving_forces.id', '=', 'scenario_conditions.project_driving_force_id')
            ->whereIn('scenario_conditions.scenario_quadrant_id', $scenario->quadrants()->select('scenario_quadrants.id'))
            ->where('scenario_conditions.review_status', 'accepted')
            ->distinct()
            ->pluck('project_driving_forces.driving_force_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Evidence as stored on a generated scenario: the signals behind each
     * S-reference, in the order the narrative was written with.
     */
    public function storedEvidence(Scenario $scenario): array
    {
        $ids = $scenario->input_signal_ids ?? [];
        $signals = Signal::with('source:id,title,url,publisher')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->values()->map(fn ($id, $index) => $signals->has($id) ? [
            'ref' => 'S'.($index + 1),
            'id' => $signals[$id]->uuid,
            'title' => $signals[$id]->title,
            'evidence_quote' => $signals[$id]->evidence_quote,
            'quote_verified' => $signals[$id]->quote_verified,
            'source_title' => $signals[$id]->source?->title,
            'source_publisher' => $signals[$id]->source?->publisher,
            'source_url' => $signals[$id]->source?->url,
        ] : ['ref' => 'S'.($index + 1), 'id' => null, 'title' => 'Sinyal sudah dihapus'])->all();
    }

    /** @param  array<int, int>  $forceIds */
    protected function acceptedSignals(array $forceIds): Collection
    {
        return Signal::with('source:id,title,url,publisher')
            ->where('review_status', 'ACCEPTED')
            ->whereHas('driving_forces', fn ($q) => $q->whereIn('driving_forces.id', $forceIds))
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->limit(self::EVIDENCE_PER_AXIS)
            ->get();
    }
}
