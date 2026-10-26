<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryRole = $this->roles->first();

        return [
            'id' => $this->uuid,
            'date_created' => $this->created_at,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $primaryRole?->display_name,
            'role_id' => $primaryRole?->id,
        ];
    }
}
