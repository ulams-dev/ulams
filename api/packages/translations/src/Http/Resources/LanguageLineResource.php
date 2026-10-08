<?php

namespace EscolaLms\Translations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LanguageLineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'text' => $this->text
        ];
    }
}
