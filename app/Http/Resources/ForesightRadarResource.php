<?php

namespace App\Http\Resources;

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
    public function toArray(Request $request): array
    {
        $short_term = null;
        $mid_term = null;
        $long_term = null;

        if ($this->time_horizon_id == 1) {
            $short_term = '✔';
        }

        if ($this->time_horizon_id == 2) {
            $mid_term = '✔';
        }

        if ($this->time_horizon_id == 3) {
            $long_term = '✔';
        }

        $item_style = "#000";

        if ($this->priority_id == 3) {
            $item_style = '#52c41a';
        }

        if ($this->priority_id == 2) {
            $item_style = '#faad14';
        }

        if ($this->priority_id == 1) {
            $item_style = '#ff4d4f';
        }

        $values = [];
        $dimensions = Dimension::count();

        for ($i = 0; $i < $dimensions; $i++) {
            array_push($values, 0);
        }

        $dimension = Dimension::all();
        foreach ($dimension as $index => $value) {
            if ($value->id == $this->driving_force?->dimension_id) {
                if ($this->time_horizon_id == 1) {
                    $values[($value->id - 1)] = rand(2, 4);
                } else if ($this->time_horizon_id == 2) {
                    $values[($value->id - 1)] = rand(5, 6);
                } else {
                    $values[($value->id - 1)] = rand(7, 8);
                }
            }
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
