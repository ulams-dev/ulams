<?php

namespace EscolaLms\AssignWithoutAccount\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserSubmissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'status' => $this->status,
            'morphable_id' => $this->morphable_id,
            'morphable_type' => $this->morphable_type,
            'created_at' => $this->created_at
        ];
    }
}
