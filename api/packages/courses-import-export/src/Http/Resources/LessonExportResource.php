<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use EscolaLms\Courses\Models\Lesson;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonExportResource extends JsonResource
{
    public function __construct(Lesson $resource)
    {
        parent::__construct($resource);
    }

    public function getResource(): Lesson
    {
        return $this->resource;
    }

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        $lesson = $this->getResource();

        return [
            'title' => $lesson->title,
            'summary' => $lesson->summary,
            'duration' => $lesson->duration,
            'active' => $lesson->active,
            'topics' => TopicExportResource::collection($lesson->topics->sortBy('order')),
            'order' => $lesson->order,
            'lessons' => LessonExportResource::collection($lesson->lessons),
        ];
    }
}
