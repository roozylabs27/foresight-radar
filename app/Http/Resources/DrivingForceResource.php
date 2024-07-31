<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrivingForceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date_created' => $this->created_at,
            'dimension' => $this->dimension->name,
            'keyword' => $this->keyword,
            'description' => $this->description,
            'status' => $this->status,
            'remark' => $this->mark,
            'approved_at' => $this->approved_at,
            'created_by' => $this->created_by_user->name,
            'updated_by' => $this->updated_by_user ? $this->updated_by_user->name : null,
        ];
    }
}
