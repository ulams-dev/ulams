<?php

namespace EscolaLms\Cmi5\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Cmi5Resource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'iri' => $this->iri,
            'title' => $this->title,
            'au' => Cmi5AuResource::collection($this->aus)
        ];
    }
}
