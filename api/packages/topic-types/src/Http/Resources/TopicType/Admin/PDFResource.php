<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Admin;

use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PDFResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            'length' => $this->resource->length,
            'page_count' => $this->resource->page_count,
            'url' => Storage::url($this->resource->value),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
