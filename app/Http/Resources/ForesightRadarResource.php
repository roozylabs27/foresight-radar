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

         if($this->priority_id == 3) {
            $item_style = '#52c41a';
        }

        if($this->priority_id == 2) {
            $item_style = '#faad14';
        }

        if($this->priority_id == 1) {
            $item_style = '#ff4d4f';
        }

        $values = [];
        $dimensions = Dimension::count();

        for($i = 0; $i< $dimensions; $i++) {
            array_push($values, 0);
        }

        $dimension = Dimension::all();
        foreach($dimension as $index => $value) {
            if($value->id == $this->driving_force->dimension_id) {
                if($this->time_horizon_id == 1) {
                    $values[($value->id-1)] = rand(2,4);
                } else if($this->time_horizon_id == 2) {
                    $values[($value->id-1)] = rand(5,6);
                } else {
                    $values[($value->id-1)] = rand(7,8);
                }
            }
        }

        return [
            'keyword' => $this->driving_force->keyword,
            'symbol' => $this->status_action->symbol,
            'dimension' => $this->driving_force->dimension->name,
            'value' => $values,
            'item_style' => $item_style,
            'priority' => $this->priority->name,
            'short_term' => $short_term,
            'mid_term' => $mid_term,
            'long_term' => $long_term,
            'status_action' => $this->status_action->code,
        ];
    }
}
