<?php

namespace EscolaLms\Settings\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SettingsCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data = [];

        foreach ($this->collection as $value) {
            $data[$value->group][$value->key] = $value->data;
        }

        return $data;
    }
}
