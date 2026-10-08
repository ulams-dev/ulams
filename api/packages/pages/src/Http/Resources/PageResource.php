<?php

namespace Ulams\Pages\Http\Resources;

use Ulams\Pages\Models\Page;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function __construct(Page $page)
    {
        $this->resource = $page;
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'author_id' => $this->author_id,
            'content' => $this->content,
            'active' => $this->active,
        ];
    }
}
