<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OverallStatusResource extends JsonResource
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
        $high = null;
        $medium = null;
        $low = null;
        $monitoring = null;
        $decided_plan = null;

        if($this->time_horizon_id == 1) {
            $short_term = '✔';
        }

        if($this->time_horizon_id == 2) {
            $mid_term = '✔';
        }

        if($this->time_horizon_id == 3) {
            $long_term = '✔';
        }

        if($this->priority_id == 3) {
            $low = '✔';
        }

        if($this->priority_id == 2) {
            $medium = '✔';
        }

        if($this->priority_id == 1) {
            $high = '✔';
        }

        if($this->status_action_id == 1) {
            $monitoring = $this->status_action->code;
        }

        if($this->status_action_id == 2) {
            $decided_plan = $this->status_action->code;
        }

        return [
            'id' => $this->uuid,
            'dimension' => $this->driving_force->dimension->name,
            'keyword' => $this->driving_force->keyword,
            'description' => $this->driving_force->description,
            'short_term' => $short_term,
            'mid_term' => $mid_term,
            'long_term' => $long_term,
            'high' => $high,
            'medium' => $medium,
            'low' => $low,
            'monitoring' => $monitoring,
            'decided_plan' => $decided_plan,
            'action_reason' => count($this->action_reasons) > 0 ? $this->action_reasons[0]->reason : null,
        ];
    }
}
