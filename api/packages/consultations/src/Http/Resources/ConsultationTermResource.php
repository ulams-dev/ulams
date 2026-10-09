<?php

namespace Ulams\Consultations\Http\Resources;

use Ulams\Auth\Traits\ResourceExtandable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ConsultationTermResource extends JsonResource
{
    use ResourceExtandable;

    public function toArray($request)
    {
        return is_string($this->resource) ? Carbon::make($this->resource) : $this->resource;
    }

    /**
     * A term is a single date, not an object. Laravel 12's ResourceCollection resolves each item and casts
     * the result to an array, which would turn every date into a one-element list; return the date itself.
     */
    public function resolve($request = null)
    {
        $value = $this->toArray($request ?: $this->resolveRequestFromContainer());

        return $value instanceof \JsonSerializable ? $value->jsonSerialize() : $value;
    }
}
