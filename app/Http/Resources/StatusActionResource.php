<?php

namespace App\Http\Resources;

use App\Models\StatusAction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusActionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $monitoring = null;
        $decided_plan = null;

        if($this->rating && $this->rating->status_action_id == 1) {
            $monitoring = $this->rating->status_action->code;
        }

        if($this->rating && $this->rating->status_action_id == 2) {
            $decided_plan =$this->rating->status_action->code;
        }

        $time_horizon = '';

        if($this->rating->time_horizon_id == 1) {
            $time_horizon .= $this->rating->time_horizon->name;
        }

        if($this->rating->time_horizon_id == 2) {
            $time_horizon .= $this->rating->time_horizon->name;
        }

        if($this->rating->time_horizon_id == 3) {
            $time_horizon .= $this->rating->time_horizon->name;
        }

        return [
            'id' => $this->rating->uuid,
            'dimension' => $this->dimension->name,
            'dimension_id' => $this->dimension->id,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'time_horizon' => $this->rating->time_horizon->name,
            'priority' => $this->rating->priority->name,
            'uncertainty_analysis' => $this->rating->uncertainty_analysis,
            'impact_analysis' => $this->rating->impact_analysis,
            'status_action_id' => $this->rating->status_action_id,
            'reasons' => collect($this->rating->action_reasons)->map(function($data) {
                $action = StatusAction::where('id', $data->status_action_id)->first();
                return [
                    'children' => $action->name . ', ' . $data->reason . ' - ' . Carbon::parse($data->date)->translatedFormat('Y-m-d H:i:s')
                ];
            })->toArray(),
            'monitoring' => $monitoring,
            'decided_plan' => $decided_plan,
        ];
    }
}
