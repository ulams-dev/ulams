<?php

namespace Ulams\TopicTypes\Http\Resources\TopicType\Export;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

class H5PResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        $topic = $this->resource->topic;
        $destination = sprintf('topic/%d/%s', $topic->id, 'export.h5p');

        // no 'id': the importer merges topicable keys into the topic data
        return [
            'value' => isset($this->resource->value) ? (int) $this->resource->value : null,
            'content' => optional($this->resource->h5pContent)->toTopicContent(),
            // package written by H5P::fixAssetPaths(), imported again by H5PTopicTypeStrategy
            'h5p_file' => $destination,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
