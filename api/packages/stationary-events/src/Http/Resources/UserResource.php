<?php

namespace EscolaLms\StationaryEvents\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'path_avatar' => $this->path_avatar,
        ];
    }
}
