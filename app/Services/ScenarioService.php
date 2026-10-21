<?php

namespace App\Services;

use App\Models\CrossImpact;
use App\Models\DrivingForce;
use App\Models\Scenario;
use App\Models\Signal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Scenario matrices: which driving forces make good axes, and which accepted
 * signals are the evidence for a scenario.
 */
class ScenarioService
{
    /** Approved forces ranked by impact x uncertainty that are offered as axes. */
    public const AXIS_CANDIDATES = 8;

    public const SUGGESTED_PAIRS = 5;

    /** Axes should be independent: neither may push the other this hard (|score|). */
    public const MAX_AXIS_INFLUENCE = 2;

    public const EVIDENCE_PER_AXIS = 30;

    /**
     * The approved forces (for the axis selects), the top candidates by
     * impact x uncertainty, pairs of them in different dimensions that do not
     * strongly influence each other, and the cross-impact scores between all
     * approved forces so the UI can warn about any other pair.
     */
    public function suggestAxes(): array
    {
        $forces = DrivingForce::where('status', 'APPROVED')
            ->with(['dimension:id,name', 'rating:id,driving_force_id,impact_analysis,uncertainty_analysis'])
            ->orderBy('keyword')
            ->get(['id', 'uuid', 'keyword', 'dimension_id']);

        $impacts = CrossImpact::whereIn('source_driving_force_id', $forces->pluck('id'))
            ->whereIn('target_driving_force_id', $forces->pluck('id'))
            ->where('score', '!=', 0)
            ->get(['source_driving_force_id', 'target_driving_force_id', 'score']);
        $scores = $impacts->mapWithKeys(fn ($impact) => [
            $impact->source_driving_force_id.'>'.$impact->target_driving_force_id => $impact->score,
        ]);

        $candidates = $forces
            ->filter(fn ($force) => $force->rating?->impact_analysis !== null && $force->rating?->uncertainty_analysis !== null)
            ->sortByDesc(fn ($force) => $force->rating->impact_analysis * $force->rating->uncertainty_analysis)
            ->take(self::AXIS_CANDIDATES)
            ->values();

        $pairs = [];
        foreach ($candidates as $i => $a) {
            foreach ($candidates->slice($i + 1) as $b) {
                $ab = (int) ($scores[$a->id.'>'.$b->id] ?? 0);
                $ba = (int) ($scores[$b->id.'>'.$a->id] ?? 0);
                if ($a->dimension_id === $b->dimension_id || max(abs($ab), abs($ba)) >= self::MAX_AXIS_INFLUENCE) {
                    continue;
                }
                $pairs[] = [
                    'a' => $this->forceSummary($a),
                    'b' => $this->forceSummary($b),
                    'influence' => ['a_to_b' => $ab, 'b_to_a' => $ba],
                    'score' => $this->priority($a) + $this->priority($b),
                ];
            }
        }
        usort($pairs, fn ($x, $y) => $y['score'] <=> $x['score']);

        $uuid = $forces->pluck('uuid', 'id');

        return [
            'forces' => $forces->map(fn ($force) => $this->forceSummary($force))->values()->all(),
            'candidates' => $candidates->map(fn ($force) => $this->forceSummary($force))->all(),
            'pairs' => array_slice($pairs, 0, self::SUGGESTED_PAIRS),
            'cross_impacts' => $impacts->map(fn ($impact) => [
                'source' => $uuid[$impact->source_driving_force_id],
                'target' => $uuid[$impact->target_driving_force_id],
                'score' => $impact->score,
            ])->values()->all(),
            'max_influence' => self::MAX_AXIS_INFLUENCE,
        ];
    }

    /**
     * Accepted signals linked to either axis force, labelled S1..Sn in a fixed
     * order (axis A first, newest first). A signal linked to both axes is
     * listed once.
     *
     * @return array<int, array{ref: string, axis: string, signal: Signal}>
     */
    public function evidenceFor(Scenario $scenario): array
    {
        $byAxis = [
            'A' => $this->acceptedSignals($scenario->axis_a_force_id),
            'B' => $this->acceptedSignals($scenario->axis_b_force_id),
        ];

        $evidence = [];
        $seen = [];
        foreach ($byAxis as $axis => $signals) {
            foreach ($signals as $signal) {
                if (isset($seen[$signal->id])) {
                    $evidence[$seen[$signal->id]]['axis'] = 'AB';

                    continue;
                }
                $seen[$signal->id] = count($evidence);
                $evidence[] = ['ref' => 'S'.(count($evidence) + 1), 'axis' => $axis, 'signal' => $signal];
            }
        }

        return $evidence;
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

    /**
     * Both axis forces must exist, differ and be approved.
     *
     * @return array{0: DrivingForce, 1: DrivingForce}
     */
    public function resolveAxes(string $axisA, string $axisB): array
    {
        $forces = DrivingForce::whereIn('uuid', [$axisA, $axisB])->get()->keyBy('uuid');
        foreach (['axis_a' => $axisA, 'axis_b' => $axisB] as $field => $uuid) {
            if (($forces[$uuid]->status ?? null) !== 'APPROVED') {
                throw ValidationException::withMessages([$field => 'Sumbu skenario harus driving force berstatus APPROVED.']);
            }
        }

        return [$forces[$axisA], $forces[$axisB]];
    }

    protected function acceptedSignals(int $forceId): Collection
    {
        return Signal::with('source:id,title,url,publisher')
            ->where('review_status', 'ACCEPTED')
            ->whereHas('driving_forces', fn ($q) => $q->where('driving_forces.id', $forceId))
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->limit(self::EVIDENCE_PER_AXIS)
            ->get();
    }

    protected function priority(DrivingForce $force): int
    {
        return (int) $force->rating?->impact_analysis * (int) $force->rating?->uncertainty_analysis;
    }

    protected function forceSummary(DrivingForce $force): array
    {
        return [
            'id' => $force->uuid,
            'keyword' => $force->keyword,
            'dimension' => $force->dimension?->name,
            'impact' => $force->rating?->impact_analysis,
            'uncertainty' => $force->rating?->uncertainty_analysis,
        ];
    }
}
