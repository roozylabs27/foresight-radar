<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->rating->uuid,
            'dimension' => $this->dimension->name,
            'dimension_id' => $this->dimension->id,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'status_action_id' => $this->rating->status_action_id,
            'monitoring' => $monitoring,
            'decided_plan' => $decided_plan,
        ];
    }
}
