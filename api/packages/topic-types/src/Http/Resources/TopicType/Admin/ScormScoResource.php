<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Admin;

use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;
use Peopleaps\Scorm\Model\ScormScoModel;

class ScormScoResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request)
    {
        $scormSco = ScormScoModel::find($this->resource->value);

        return [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            // @phpstan-ignore-next-line
            'uuid' => $scormSco ? $scormSco->uuid : null,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
