<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeHorizonResource extends JsonResource
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

        if($this->rating && $this->rating->time_horizon_id == 1) {
            $short_term = '✔';
        }

        if($this->rating && $this->rating->time_horizon_id == 2) {
            $mid_term = '✔';
        }

        if($this->rating && $this->rating->time_horizon_id == 3) {
            $long_term = '✔';
        }

        return [
            'id' => $this->id,
            'dimension' => $this->dimension->name,
            'dimension_id' => $this->dimension->id,
            'time_horizon_id' => $this->rating ? $this->rating->time_horizon_id : null,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'short_term' => $short_term,
            'mid_term' => $mid_term,
            'long_term' => $long_term,
        ];
    }
}
