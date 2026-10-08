<?php

namespace Ulams\TopicTypes\Http\Resources\TopicType\Export;

use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Ulams\TopicTypes\Services\TopicTypeService;
use Illuminate\Http\Resources\Json\JsonResource;

class PDFResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'value' => TopicTypeService::sanitizePath($this->resource->value),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
