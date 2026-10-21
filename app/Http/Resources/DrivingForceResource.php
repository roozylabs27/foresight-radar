<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrivingForceResource extends JsonResource
{
    /**
     * Resolve the current pipeline stage and next action URL.
     */
    private function resolvePipelineStatus(): array
    {
        $rating = $this->relationLoaded('rating') ? $this->rating : null;

        if (!$rating || is_null($rating->time_horizon_id)) {
            return [
                'step' => 2,
                'total_steps' => 5,
                'label' => 'Step 2/5: Time Horizon',
                'badge_color' => 'gold',
                'status' => 'pending_horizon',
                'next_url' => '/time-horizon',
                'next_label' => 'Time Horizon',
            ];
        }

        if (is_null($rating->impact_analysis) || is_null($rating->uncertainty_analysis)) {
            return [
                'step' => 3,
                'total_steps' => 5,
                'label' => 'Step 3/5: Urgency Rating',
                'badge_color' => 'orange',
                'status' => 'pending_urgency',
                'next_url' => '/rating-urgency',
                'next_label' => 'Rate Urgency',
            ];
        }

        if (is_null($rating->status_action_id)) {
            return [
                'step' => 4,
                'total_steps' => 5,
                'label' => 'Step 4/5: Status Action',
                'badge_color' => 'cyan',
                'status' => 'pending_action',
                'next_url' => '/status-action',
                'next_label' => 'Status Action',
            ];
        }

        $statusStr = $this->status ?? 'PENDING';

        return match ($statusStr) {
            'APPROVED' => [
                'step' => 5,
                'total_steps' => 5,
                'label' => 'Approved',
                'badge_color' => 'green',
                'status' => 'approved',
                'next_url' => null,
                'next_label' => null,
            ],
            'REJECTED' => [
                'step' => 5,
                'total_steps' => 5,
                'label' => 'Rejected',
                'badge_color' => 'red',
                'status' => 'rejected',
                'next_url' => null,
                'next_label' => null,
            ],
            'CLOSED' => [
                'step' => 5,
                'total_steps' => 5,
                'label' => 'Closed',
                'badge_color' => 'default',
                'status' => 'closed',
                'next_url' => null,
                'next_label' => null,
            ],
            default => [
                'step' => 5,
                'total_steps' => 5,
                'label' => 'Step 5/5: Pending Approval',
                'badge_color' => 'blue',
                'status' => 'pending_approval',
                'next_url' => '/approval-items',
                'next_label' => 'Review Approval',
            ],
        };
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supporting = $this->relationLoaded('supporting_signals')
            ? $this->supporting_signals->map(function ($signal) {
                return [
                    'id' => $signal->uuid,
                    'title' => $signal->title,
                    'summary' => $signal->summary,
                    'source_title' => $signal->source?->title,
                    'source_url' => $signal->source?->url,
                    'notes' => $signal->pivot?->notes,
                ];
            })->values()->toArray()
            : [];

        $sourceSignal = $this->relationLoaded('source_signal') && $this->source_signal
            ? [
                'id' => $this->source_signal->uuid,
                'title' => $this->source_signal->title,
                'summary' => $this->source_signal->summary,
                'source_title' => $this->source_signal->source?->title,
                'source_url' => $this->source_signal->source?->url,
            ]
            : null;

        $signalsCount = ($sourceSignal ? 1 : 0) + count($supporting);
        $pipeline = $this->resolvePipelineStatus();

        return [
            'id' => $this->uuid,
            'date_created' => $this->created_at,
            'dimension' => $this->dimension?->name,
            'dimension_id' => $this->dimension?->id,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'pic' => $this->pic_user?->name,
            'pic_id' => $this->pic_user?->id,
            'status' => $this->status,
            'signals_count' => $signalsCount,
            'source_signal' => $sourceSignal,
            'supporting_signals' => $supporting,
            'pipeline' => $pipeline,
        ];
    }
}
