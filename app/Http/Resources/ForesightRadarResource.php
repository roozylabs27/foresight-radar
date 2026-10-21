<?php

namespace App\Http\Resources;

use App\Enums\PriorityLevel;
use App\Enums\TimeHorizonType;
use App\Models\Dimension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForesightRadarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    /** Radar value range of each horizon band, inner to outer (see resources/js/lib/radarLayout.js). */
    public const HORIZON_BANDS = [[2.0, 4.0], [5.0, 6.0], [7.0, 8.0]];

    /**
     * Dimension ids in display order; set by the reporting service so the
     * collection reads them once.
     *
     * @var array<int, int>|null
     */
    public ?array $dimensionOrder = null;

    public function withDimensionOrder(array $dimensionOrder): static
    {
        $this->dimensionOrder = $dimensionOrder;

        return $this;
    }

    /**
     * Distance from the radar centre: the horizon picks the band, and within
     * it a higher impact sits closer to the centre. An unset horizon falls in
     * the Mid Term band and an unrated impact counts as 5.
     */
    public static function radarValue(?TimeHorizonType $horizon, mixed $impact): float
    {
        [$inner, $outer] = self::HORIZON_BANDS[($horizon ?? TimeHorizonType::MID_TERM)->band()];
        $impact = $impact === null ? 5 : max(1, min(10, (int) $impact));

        return round($outer - ($outer - $inner) * ($impact - 1) / 9, 2);
    }

    public function toArray(Request $request): array
    {
        $horizon = TimeHorizonType::fromModel($this->time_horizon);
        $short_term = $horizon === TimeHorizonType::SHORT_TERM ? '✔' : null;
        $mid_term = $horizon === TimeHorizonType::MID_TERM ? '✔' : null;
        $long_term = $horizon === TimeHorizonType::LONG_TERM ? '✔' : null;

        $item_style = PriorityLevel::fromModel($this->priority)?->radarColor() ?? '#000';

        $dimensionOrder = $this->dimensionOrder ?? Dimension::orderBy('id')->pluck('id')->all();
        $values = array_fill(0, count($dimensionOrder), 0);
        $position = array_search($this->driving_force?->dimension_id, $dimensionOrder, true);
        if ($position !== false) {
            $values[$position] = self::radarValue($horizon, $this->impact_analysis);
        }

        $df = $this->driving_force;
        $supporting = ($df && $df->relationLoaded('supporting_signals'))
            ? $df->supporting_signals->map(function ($signal) {
                return [
                    'id' => $signal->uuid,
                    'title' => $signal->title,
                    'summary' => $signal->summary,
                    'evidence_quote' => $signal->evidence_quote,
                    'confidence_score' => $signal->confidence_score,
                    'source_title' => $signal->source?->title,
                    'source_url' => $signal->source?->url,
                    'notes' => $signal->pivot?->notes,
                ];
            })->values()->toArray()
            : [];

        $sourceSignal = ($df && $df->relationLoaded('source_signal') && $df->source_signal)
            ? [
                'id' => $df->source_signal->uuid,
                'title' => $df->source_signal->title,
                'summary' => $df->source_signal->summary,
                'evidence_quote' => $df->source_signal->evidence_quote,
                'confidence_score' => $df->source_signal->confidence_score,
                'source_title' => $df->source_signal->source?->title,
                'source_url' => $df->source_signal->source?->url,
            ]
            : null;

        $signalsCount = ($sourceSignal ? 1 : 0) + count($supporting);
        $latestReason = $this->relationLoaded('action_reasons')
            ? $this->action_reasons->first()?->reason
            : null;

        return [
            'id' => $df?->uuid,
            'keyword' => $df?->keyword,
            'description' => $df?->description,
            'pic' => $df?->pic_user?->name,
            'approved_at' => $df?->approved_at,
            'symbol' => $this->status_action?->symbol,
            'dimension' => $df?->dimension?->name,
            'time_horizon' => $this->time_horizon?->name,
            'value' => $values,
            'item_style' => $item_style,
            'priority' => $this->priority?->name,
            'impact_analysis' => $this->impact_analysis,
            'uncertainty_analysis' => $this->uncertainty_analysis,
            'short_term' => $short_term,
            'mid_term' => $mid_term,
            'long_term' => $long_term,
            'status_action' => $this->status_action?->code,
            'status_action_name' => $this->status_action?->name,
            'reason' => $latestReason,
            'signals_count' => $signalsCount,
            'source_signal' => $sourceSignal,
            'supporting_signals' => $supporting,
        ];
    }
}
