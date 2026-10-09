<?php

namespace Ulams\LiaScript\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * @mixin LiaScriptTopic
 */
class LiaScriptTopicResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'title' => $this->document?->title,
            'version' => $this->document?->current_version,
        ];
    }
}
