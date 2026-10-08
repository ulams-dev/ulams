<?php

namespace Ulams\TopicTypes\Http\Resources\TopicType\Client;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * `content` is a light summary; the player model comes from the H5P service
 * (GET /h5p/contents/{value}/play).
 */
class H5PResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'value' => isset($this->resource->value) ? (int) $this->resource->value : null,
            'content' => optional($this->resource->h5pContent)->toTopicContent(),
        ];
    }
}
