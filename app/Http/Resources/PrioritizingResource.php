<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrioritizingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'dimension' => $df?->dimension?->name,
            'pic' => $df?->pic_user?->name,
            'approved_at' => $df?->approved_at,
            'priority' => $this->priority?->name,
            'time_horizon' => $this->time_horizon?->name,
            'status_action' => $this->status_action?->code,
            'status_action_name' => $this->status_action?->name,
            'impact_analysis' => $this->impact_analysis,
            'uncertainty_analysis' => $this->uncertainty_analysis,
            'reason' => $latestReason,
            'signals_count' => $signalsCount,
            'source_signal' => $sourceSignal,
            'supporting_signals' => $supporting,
            'position' => [($this->impact_analysis - 1), ($this->uncertainty_analysis - 1)],
        ];
    }
}
