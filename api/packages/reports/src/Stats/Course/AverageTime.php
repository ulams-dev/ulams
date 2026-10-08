<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
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
        $results = Course::dontCache()->selectRaw($courseTable . '.id, ' . $courseProgressTable . '.user_id, SUM(' . $courseProgressTable . '.seconds) as time')
            ->leftJoin($lessonTable, $courseTable . '.id', '=', $lessonTable . '.course_id')
            ->leftJoin($topicTable, $lessonTable . '.id', '=', $topicTable . '.lesson_id')
            ->leftJoin($courseProgressTable, $topicTable . '.id', '=', $courseProgressTable . '.topic_id')
            ->where($courseTable . '.id', '=', $this->course->getKey())
            ->groupBy($courseTable . '.id', $courseProgressTable . '.user_id')
            ->get();

        return $results->average('time') ?? 0;
    }
}
