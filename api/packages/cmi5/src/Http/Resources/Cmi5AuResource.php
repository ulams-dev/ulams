<?php

namespace EscolaLms\Cmi5\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Cmi5AuResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'iri' => $this->iri,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
