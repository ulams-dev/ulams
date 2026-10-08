<?php


namespace Ulams\Courses\Http\Resources;

use Ulams\Categories\Http\Resources\CategoryResource;
use Ulams\Courses\Models\Course;
use Ulams\Courses\ValueObjects\CourseContent;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResource extends JsonResource
{
    public function __construct(CourseProgressCollection $resource)
    {
        $this->resource = $resource;
    }

    public function getResource(): CourseProgressCollection
    {
        return $this->resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $this->withoutWrapping();
        /** @var Course $course */
        $course = $this->getResource()->getCourse();
        return [
            'course' => CourseResource::make(CourseContent::make($course)),
            'categories' => CategoryResource::collection($course->categories),
            'tags' => $course->tags,
            'progress' => $this->getResource()->getProgress()->toArray(),
            'start_date' => $this->getResource()->getStartDate(),
            'finish_date' => $this->getResource()->isFinished() ? $this->getResource()->getFinishDate() : null,
            'deadline' => $this->getResource()->getDeadline(),
            'end_date' => $this->getResource()->getEndDate(),
            'total_spent_time' => $this->getResource()->getTotalSpentTime() ?? 0,
        ];
    }
}
