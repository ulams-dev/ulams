<?php

namespace EscolaLms\Consultations\Http\Resources;

use EscolaLms\Auth\Traits\ResourceExtandable;
use EscolaLms\ModelFields\Enum\MetaFieldVisibilityEnum;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationAuthorResource extends JsonResource
{
    use ResourceExtandable;

    public function toArray($request)
    {
        $fields = array_merge(
            $this->resource->toArray(),
            ['categories' => $this->resource->categories],
            ModelFields::getExtraAttributesValues($this->resource, MetaFieldVisibilityEnum::PUBLIC)
        );

        return self::apply($fields, $this);
    }
}
