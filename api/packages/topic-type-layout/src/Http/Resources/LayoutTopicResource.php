<?php

namespace Ulams\TopicTypeLayout\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\TopicTypeLayout\Models\LayoutTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * @mixin LayoutTopic
 */
class LayoutTopicResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document,
            'schema_version' => $this->schema_version,
            'markdown_fallback' => $this->markdown_fallback,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
