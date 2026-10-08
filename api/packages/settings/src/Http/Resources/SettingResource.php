<?php

namespace EscolaLms\Settings\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'group' => $this->resource->group,
            'value' => $this->resource->value,
            'public' => $this->resource->public,
            'enumerable' => $this->resource->enumerable,
            'sort' => $this->resource->sort,
            'type' => $this->resource->type,
            'data' => $this->resource->data,
        ];
    }
}
