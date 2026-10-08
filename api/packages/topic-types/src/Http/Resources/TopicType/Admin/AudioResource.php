<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Admin;

use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AudioResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            'url' => Storage::url($this->resource->value),
            'length' => $this->resource->length,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
