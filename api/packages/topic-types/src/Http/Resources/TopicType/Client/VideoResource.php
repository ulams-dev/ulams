<?php

namespace EscolaLms\TopicTypes\Http\Resources\TopicType\Client;

use EscolaLms\Auth\Traits\ResourceExtandable;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VideoResource extends JsonResource implements TopicTypeResourceContract
{
    use ResourceExtandable;

    public function toArray($request)
    {
        $fields = [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            'url' => $this->resource->value ? Storage::url($this->resource->value) : null,
            'poster' => $this->resource->poster,
            'poster_url' => $this->resource->poster ? Storage::url($this->resource->poster) : null,
            'width' => $this->resource->width,
            'height' => $this->resource->height,
            'length' => $this->resource->length,
        ];

        return self::apply($fields, $this);
    }

    public static function apply(array $fields, JsonResource $thisObj): array {
        foreach (self::$extensions as $extension) {
            $fields = array_merge($fields, $extension($thisObj));
        }
        return $fields;
    }
}
