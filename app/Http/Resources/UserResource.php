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
        return [
            'id' => $this->uuid,
            'date_created' => $this->created_at,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->roles[0]->display_name,
            'role_id' => $this->roles[0]->id,
        ];
    }
}
