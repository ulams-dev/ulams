<?php

namespace EscolaLms\Categories\Http\Resources;

use EscolaLms\Categories\Models\Category;

class CategoryTreeResource extends CategoryResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $children = $this->children->filter(fn (Category $child) => $child->is_active);
        return parent::toArray($request) + [
            'subcategories' => self::collection($children),
        ];
    }
}
