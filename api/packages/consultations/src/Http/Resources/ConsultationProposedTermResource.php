<?php

namespace EscolaLms\Consultations\Http\Resources;

use EscolaLms\Auth\Traits\ResourceExtandable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ConsultationProposedTermResource extends JsonResource
{
    use ResourceExtandable;

    public function toArray($request)
    {
        return is_string($this->resource) ? Carbon::make($this->resource) : $this->resource;
    }
}
