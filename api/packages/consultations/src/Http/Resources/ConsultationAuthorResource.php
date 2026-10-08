<?php

namespace Ulams\Consultations\Http\Resources;

use Ulams\Auth\Traits\ResourceExtandable;
use Ulams\ModelFields\Enum\MetaFieldVisibilityEnum;
use Ulams\ModelFields\Facades\ModelFields;
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
