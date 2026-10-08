<?php

namespace Ulams\Courses\Http\Resources;

use Ulams\Courses\Models\Topic;

class LessonSimpleResource extends LessonResource
{
    public function toArray($request): array
    {
        return parent::toArray($request) +
            [
                'topics' => TopicSimpleResource::collection($this->topics->filter(fn (Topic $topic) => $topic->active)->sortBy('order')),
            ];
    }
}
