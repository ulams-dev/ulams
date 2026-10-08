<?php

namespace Ulams\TopicTypes\Http\Resources\TopicType\Export;

use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Ulams\TopicTypes\Services\TopicTypeService;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'value' => TopicTypeService::sanitizePath($this->resource->value),
            'width' => $this->resource->width,
            'height' => $this->resource->height,
        ];
    }
}
