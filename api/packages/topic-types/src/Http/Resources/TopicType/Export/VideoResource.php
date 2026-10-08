<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Export;

use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use EscolaLms\TopicTypes\Services\TopicTypeService;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'value' => TopicTypeService::sanitizePath($this->resource->value),
            'poster' => $this->resource->poster ? TopicTypeService::sanitizePath($this->resource->poster) : null,
            'width' => $this->resource->width,
            'height' => $this->resource->height,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
