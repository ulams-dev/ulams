<?php

namespace Ulams\Reports\Stats\Course;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Illuminate\Database\Eloquent\Collection;

class AverageTime extends AbstractCourseStat
{
    public function calculate(): int
    {
        $courseTable = $this->course->getTable();
        $lessonTable = (new Lesson())->getTable();
        $topicTable = (new Topic())->getTable();
        $courseProgressTable = (new CourseProgress())->getTable();

        /** @var Collection $results */
        $results = Course::query()->selectRaw($courseTable . '.id, ' . $courseProgressTable . '.user_id, SUM(' . $courseProgressTable . '.seconds) as time')
            ->leftJoin($lessonTable, $courseTable . '.id', '=', $lessonTable . '.course_id')
            ->leftJoin($topicTable, $lessonTable . '.id', '=', $topicTable . '.lesson_id')
            ->leftJoin($courseProgressTable, $topicTable . '.id', '=', $courseProgressTable . '.topic_id')
            ->where($courseTable . '.id', '=', $this->course->getKey())
            ->groupBy($courseTable . '.id', $courseProgressTable . '.user_id')
            ->get();

        return $results->average('time') ?? 0;
    }
}
