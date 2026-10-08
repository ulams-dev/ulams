<?php

namespace EscolaLms\Tasks\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray($request): array
    {
        $course = $this->course;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'parent_lesson_id' => $this->parent_lesson_id,
            'course_id' => $course?->getKey(),
            'course_title' => $course?->title,
        ];
    }
}
