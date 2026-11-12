<?php

namespace App\Services;

use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\IndicatorThresholdEvent;
use App\Support\ThresholdEvaluator;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Audit U4 (AUD-04, AUD-05): threshold crossings decided on the server.
 *
 * - An indicator with a numeric threshold (operator + value): the server compares the observation's
 *   numeric value; whatever the client sent is ignored. No numeric value means no decision.
 * - A qualitative indicator: a person may flag a crossing, with a note (checked by the caller).
 *
 * Only accepted observations count: a proposal (World Bank, AI match) is evaluated when a person
 * confirms it. A crossing is recorded once per observation and flags the framework, its scenarios
 * and the exercise's strategic responses for review, with a reason. Nothing is deleted.
 */
class IndicatorThresholdService
{
    public function __construct(protected StalenessService $staleness) {}

    /** Whether the observation is decided by the indicator's numeric rule (the client flag then does not count). */
    public function decidesNumerically(EarlyWarningIndicator $indicator): bool
    {
        return ThresholdEvaluator::isNumeric($indicator->threshold_operator, $indicator->threshold_value);
    }

    /**
     * Decide and record the crossing of an accepted observation. `$manualFlag` is a person's flag,
     * used only for an indicator without a numeric threshold. Returns whether it crossed.
     */
    public function evaluate(IndicatorObservation $observation, bool $manualFlag = false): bool
    {
        $indicator = $observation->indicator;
        if ($observation->review_status !== 'accepted') {
            return false;
        }

        $numeric = $this->decidesNumerically($indicator);
        $crossed = $numeric
            ? (bool) ThresholdEvaluator::crosses($indicator->threshold_operator, $indicator->threshold_value, $observation->value_numeric)
            : $manualFlag;

        DB::transaction(function () use ($observation, $indicator, $numeric, $crossed) {
            $observation->forceFill(['crossed_threshold' => $crossed])->save();
            if (! $crossed || IndicatorThresholdEvent::where('indicator_observation_id', $observation->id)->exists()) {
                return;
            }

            IndicatorThresholdEvent::create([
                'uuid' => (string) Uuid::uuid4(),
                'tenant_id' => $indicator->getAttribute('tenant_id'),
                'early_warning_indicator_id' => $indicator->id,
                'indicator_observation_id' => $observation->id,
                'kind' => $numeric ? 'numeric' : 'manual',
                'operator' => $numeric ? $indicator->threshold_operator : null,
                'threshold_value' => $numeric ? $indicator->threshold_value : null,
                'observed_value' => $observation->value_numeric,
                'observed_at' => $observation->observed_at,
            ]);

            $rule = ThresholdEvaluator::describe($indicator->threshold_operator, $indicator->threshold_value);
            $reason = "Indikator {$indicator->name} melewati ambang batas"
                .($rule && $numeric ? " ({$rule}; nilai {$this->number($observation->value_numeric)})" : '')
                ." pada {$observation->observed_at->toDateString()}";
            $project = $indicator->project;
            $this->staleness->markFramework($project, $reason, downstream: false);
            $this->staleness->markResponses($project, $reason);
        });

        return $crossed;
    }

    /** The observation is going away: its event stays as history, closed. Review flags stay until the team clears them. */
    public function close(IndicatorObservation $observation): void
    {
        IndicatorThresholdEvent::where('indicator_observation_id', $observation->id)
            ->update(['closed_at' => now(), 'indicator_observation_id' => null]);
    }

    private function number(?float $value): string
    {
        return $value === null ? '-' : rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
