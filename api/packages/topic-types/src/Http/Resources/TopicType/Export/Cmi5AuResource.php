<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Export;

use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use EscolaLms\TopicTypes\Models\TopicContent\Cmi5Au;
use Illuminate\Http\Resources\Json\JsonResource;

class Cmi5AuResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        $topic = $this->resource->topic;
        $destination = sprintf('topic/%d/%s', $topic->resource->id, 'export.zip');
        $cmi5Au = Cmi5Au::find($this->resource->value);

        return [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            // @phpstan-ignore-next-line
            'iri' => $cmi5Au ? $cmi5Au->resource->iri : null,
            // @phpstan-ignore-next-line
            'url' => $cmi5Au ? $cmi5Au->resource->url : null,
            'cmi5_file' => $destination,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
