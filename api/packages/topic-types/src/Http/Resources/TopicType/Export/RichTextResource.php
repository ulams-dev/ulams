<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Export;

use EscolaLms\TopicTypes\Facades\Markdown;
use EscolaLms\TopicTypes\Facades\Path;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;

class RichTextResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'value' => Path::sanitizePathForExport(Markdown::getImagesPathsWithoutImageApi($this->resource->value)),
            'asset_folder' => $this->resource->topic->getKey(),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
