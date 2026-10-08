<?php

namespace EscolaLms\Tasks\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    public function toArray($request): array
    {
        $course = $this->lesson?->course;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->topicable_type,
            'lesson_id' => $this->lesson?->getKey(),
            'course_id' => $course?->getKey(),
            'course_title' => $course?->title,
        ];
    }
}
