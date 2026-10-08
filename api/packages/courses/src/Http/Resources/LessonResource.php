<?php

namespace Ulams\Courses\Http\Resources;

use Ulams\Courses\Models\Lesson;
use Ulams\ModelFields\Enum\MetaFieldVisibilityEnum;
use Ulams\ModelFields\Facades\ModelFields;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'summary' => $this->resource->summary,
            'duration' => $this->resource->duration,
            'active' => $this->resource->active,
            'order' => $this->resource->order,
            'course_id' => $this->resource->course_id,
            'active_from' => $this->resource->active_from,
            'active_to' => $this->resource->active_to,
            'lessons' => LessonSimpleResource::collection($this->resource->lessons->filter(fn (Lesson $lesson) => $lesson->active)->sortBy('order')),
            ...ModelFields::getExtraAttributesValues($this->resource, MetaFieldVisibilityEnum::PUBLIC)
        ];
    }
}
