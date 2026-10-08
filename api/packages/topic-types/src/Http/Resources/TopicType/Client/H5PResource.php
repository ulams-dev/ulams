<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Client;

use EscolaLms\HeadlessH5P\Services\Contracts\HeadlessH5PServiceContract;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;

class H5PResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            'content' => isset($this->resource->value) ? app(HeadlessH5PServiceContract::class)->getContentSettings($this->resource->value) : null,
        ];
    }
}
