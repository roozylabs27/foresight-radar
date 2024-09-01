<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingUrgencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->rating->uuid,
            'driving_force_id' => $this->id,
            'dimension' => $this->dimension->name,
            'dimension_id' => $this->dimension->id,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'impact' => $this->rating ? $this->rating->impact_analysis : null,
            'uncertainty' => $this->rating ? $this->rating->uncertainty_analysis : null,
        ];
    }
}
