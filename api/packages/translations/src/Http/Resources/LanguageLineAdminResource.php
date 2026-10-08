<?php

namespace Ulams\Translations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LanguageLineAdminResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'text' => $this->text,
            'public' => $this->public,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
