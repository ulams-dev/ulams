<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'is_active' => $this->resource->is_active,
            'parent' => $this->resource->parent_id ? CategoryExportResource::make($this->resource->parent) : null,
            'icon' => $this->resource->icon,
            'icon_class' => $this->resource->icon_class,
        ];
    }
}
