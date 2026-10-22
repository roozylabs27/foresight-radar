<?php

namespace App\Services;

use App\Enums\StatusActionType;
use App\Models\CrossImpact;
use App\Models\StatusAction;
use Illuminate\Validation\ValidationException;

class ScenarioSimulationService
{
    public function __construct(
        protected ReasoningEngineService $reasoningEngine,
    ) {}

    /**
     * Simulate "what-if" overrides on approved driving force ratings.
     *
     * @param  string|array|null  $dateRange
     * @param  array<int, array{force: string, status_action?: string, impact?: int, uncertainty?: int}>  $overrides
     */
    public function simulate(mixed $dateRange, mixed $dimension, array $overrides, bool $propagate = false): array
    {
        $date = is_array($dateRange) ? $dateRange : (is_string($dateRange) ? explode(' - ', $dateRange) : null);
        $ratings = $this->reasoningEngine->loadRatings($date, $dimension);

        $ratingsByUuid = [];
        foreach ($ratings as $r) {
            if ($r->driving_force?->uuid) {
                $ratingsByUuid[$r->driving_force->uuid] = $r;
            }
        }

        // Validate that all forces in overrides exist within the current filter scope
        foreach ($overrides as $override) {
            $forceUuid = $override['force'] ?? null;
            if (! $forceUuid || ! isset($ratingsByUuid[$forceUuid])) {
                throw ValidationException::withMessages([
                    'overrides' => "Driving force [{$forceUuid}] tidak ditemukan pada cakupan filter saat ini.",
                ]);
            }
        }

        // Compute baseline
        $baseResilience = $this->reasoningEngine->explainResilienceIndex($ratings);
        $baseGrowth = $this->reasoningEngine->explainGrowthIndex($ratings);
        $baseReadiness = $this->reasoningEngine->determineReadinessStatus($baseResilience['index'], $baseGrowth['index']);

        // Cache StatusAction models
        $statusActionModels = [
            'monitoring' => StatusAction::where('code', StatusActionType::MONITORING->value)->first()
                ?? StatusAction::where('name', 'Monitoring')->first(),
            'decided_plan' => StatusAction::where('code', StatusActionType::DECIDED_PLAN->value)->first()
                ?? StatusAction::where('name', 'Decided Plan')->first(),
        ];

        // Clone ratings and apply overrides
        $clonedRatings = $ratings->map(function ($rating) {
            $clone = clone $rating;
            if ($rating->relationLoaded('driving_force')) {
                $clone->setRelation('driving_force', clone $rating->driving_force);
                if ($rating->driving_force->relationLoaded('dimension')) {
                    $clone->driving_force->setRelation('dimension', clone $rating->driving_force->dimension);
                }
            }
            if ($rating->relationLoaded('status_action') && $rating->status_action) {
                $clone->setRelation('status_action', clone $rating->status_action);
            }
            if ($rating->relationLoaded('priority') && $rating->priority) {
                $clone->setRelation('priority', clone $rating->priority);
            }
            if ($rating->relationLoaded('time_horizon') && $rating->time_horizon) {
                $clone->setRelation('time_horizon', clone $rating->time_horizon);
            }

            return $clone;
        });

        $clonedByUuid = [];
        foreach ($clonedRatings as $r) {
            if ($r->driving_force?->uuid) {
                $clonedByUuid[$r->driving_force->uuid] = $r;
            }
        }

        $appliedOverrides = [];
        foreach ($overrides as $override) {
            $forceUuid = $override['force'];
            $rating = $clonedByUuid[$forceUuid];

            $before = [
                'impact' => $rating->impact_analysis,
                'uncertainty' => $rating->uncertainty_analysis,
                'status_action' => StatusActionType::fromModel($rating->status_action)?->name ?? 'monitoring',
            ];

            if (isset($override['impact'])) {
                $rating->impact_analysis = (int) $override['impact'];
            }
            if (isset($override['uncertainty'])) {
                $rating->uncertainty_analysis = (int) $override['uncertainty'];
            }
            if (isset($override['status_action']) && isset($statusActionModels[$override['status_action']])) {
                $rating->status_action_id = $statusActionModels[$override['status_action']]->id;
                $rating->setRelation('status_action', $statusActionModels[$override['status_action']]);
            }

            $appliedOverrides[] = [
                'force' => $forceUuid,
                'keyword' => $rating->driving_force?->keyword,
                'before' => $before,
                'after' => [
                    'impact' => $rating->impact_analysis,
                    'uncertainty' => $rating->uncertainty_analysis,
                    'status_action' => StatusActionType::fromModel($rating->status_action)?->name ?? 'monitoring',
                ],
            ];
        }

        $propagations = [];
        if ($propagate) {
            $sourceForceIds = [];
            foreach ($appliedOverrides as $ao) {
                $u = $ao['force'];
                if (isset($clonedByUuid[$u]->driving_force_id)) {
                    $sourceForceIds[$clonedByUuid[$u]->driving_force_id] = $u;
                }
            }

            $allForceIds = [];
            foreach ($clonedRatings as $r) {
                if ($r->driving_force_id && $r->driving_force?->uuid) {
                    $allForceIds[$r->driving_force_id] = $r->driving_force->uuid;
                }
            }

            if (! empty($sourceForceIds) && ! empty($allForceIds)) {
                $edges = CrossImpact::query()
                    ->whereIn('source_driving_force_id', array_keys($sourceForceIds))
                    ->whereIn('target_driving_force_id', array_keys($allForceIds))
                    ->where('score', '!=', 0)
                    ->get();

                foreach ($edges as $edge) {
                    $targetUuid = $allForceIds[$edge->target_driving_force_id] ?? null;
                    $sourceUuid = $sourceForceIds[$edge->source_driving_force_id] ?? null;

                    if (! $targetUuid || ! $sourceUuid || ! isset($clonedByUuid[$targetUuid])) {
                        continue;
                    }

                    $targetRating = $clonedByUuid[$targetUuid];
                    $sourceRating = $clonedByUuid[$sourceUuid];

                    $scoreNorm = $edge->score / 3.0;
                    $isSourceDecided = StatusActionType::fromModel($sourceRating->status_action) === StatusActionType::DECIDED_PLAN;

                    if ($isSourceDecided) {
                        $deltaU = (int) round(-$scoreNorm * 1.5);
                        $deltaI = (int) round($scoreNorm * 1.0);
                    } else {
                        $deltaU = (int) round($scoreNorm * 0.5);
                        $deltaI = 0;
                    }

                    $oldImpact = $targetRating->impact_analysis;
                    $oldUncertainty = $targetRating->uncertainty_analysis;

                    $newImpact = max(1, min(10, $oldImpact + $deltaI));
                    $newUncertainty = max(1, min(10, $oldUncertainty + $deltaU));

                    $targetRating->impact_analysis = $newImpact;
                    $targetRating->uncertainty_analysis = $newUncertainty;

                    $propagations[] = [
                        'source' => $sourceUuid,
                        'source_keyword' => $sourceRating->driving_force?->keyword ?? 'Unknown',
                        'target' => $targetUuid,
                        'target_keyword' => $targetRating->driving_force?->keyword ?? 'Unknown',
                        'score' => $edge->score,
                        'induced_delta_impact' => $newImpact - $oldImpact,
                        'induced_delta_uncertainty' => $newUncertainty - $oldUncertainty,
                    ];
                }
            }
        }

        // Recalculate simulation
        $simResilience = $this->reasoningEngine->explainResilienceIndex($clonedRatings);
        $simGrowth = $this->reasoningEngine->explainGrowthIndex($clonedRatings);
        $simReadiness = $this->reasoningEngine->determineReadinessStatus($simResilience['index'], $simGrowth['index']);

        $resilienceDelta = round($simResilience['index'] - $baseResilience['index'], 1);
        $growthDelta = round($simGrowth['index'] - $baseGrowth['index'], 1);
        $postureChanged = ($baseReadiness['status'] !== $simReadiness['status']);

        return [
            'baseline' => [
                'growth_index' => $baseGrowth['index'],
                'resilience_index' => $baseResilience['index'],
                'readiness_status' => $baseReadiness['status'],
                'readiness_badge' => $baseReadiness['badge'],
                'readiness_color' => $baseReadiness['color'],
                'explanation' => [
                    'growth' => $baseGrowth,
                    'resilience' => $baseResilience,
                ],
            ],
            'simulation' => [
                'growth_index' => $simGrowth['index'],
                'resilience_index' => $simResilience['index'],
                'readiness_status' => $simReadiness['status'],
                'readiness_badge' => $simReadiness['badge'],
                'readiness_color' => $simReadiness['color'],
                'explanation' => [
                    'growth' => $simGrowth,
                    'resilience' => $simResilience,
                ],
            ],
            'deltas' => [
                'resilience' => $resilienceDelta,
                'growth' => $growthDelta,
            ],
            'posture_changed' => $postureChanged,
            'overrides' => $appliedOverrides,
            'propagation_enabled' => $propagate,
            'propagations' => $propagations,
        ];
    }
}
