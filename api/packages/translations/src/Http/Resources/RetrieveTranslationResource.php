<?php

namespace EscolaLms\Translations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RetrieveTranslationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}