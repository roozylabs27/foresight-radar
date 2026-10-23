<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClosedItemsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status_action = $this->rating?->status_action;
        $action_reason = $this->rating?->action_reasons
            ?->sortBy([['date', 'desc'], ['id', 'desc']])
            ->first();

        return [
            'id' => $this->uuid,
            'closed_at' => $this->closed_at,
            'date_created' => $this->created_at,
            'dimension' => $this->dimension?->name,
            'dimension_id' => $this->dimension?->id,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'pic' => $this->pic_user?->name,
            'status' => $this->status,
            'time_horizon' => $this->rating?->time_horizon?->name,
            'priority' => $this->rating?->priority?->name,
            'uncertainty_analysis' => $this->rating?->uncertainty_analysis,
            'impact_analysis' => $this->rating?->impact_analysis,
            'reason' => $action_reason ? $action_reason->reason : null,
            'status_action' => $status_action?->code,
        ];
    }
}
