<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Export;

use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use EscolaLms\TopicTypes\Services\TopicTypeService;
use Illuminate\Http\Resources\Json\JsonResource;

class AudioResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'value' => TopicTypeService::sanitizePath($this->resource->value),
            'length' => $this->resource->length,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
