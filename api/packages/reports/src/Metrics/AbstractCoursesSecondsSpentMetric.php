<?php

namespace Ulams\Reports\Metrics;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class AbstractCoursesSecondsSpentMetric extends AbstractCoursesMetric
{
    public function calculate(?int $limit = null): Collection
    {
        $courseTable = (new Course())->getTable();
        $lessonTable = (new Lesson())->getTable();
        $topicTable = (new Topic())->getTable();
        $courseProgressTable = (new CourseProgress())->getTable();

        $query = Course::query()->selectRaw($courseTable . '.id, ' . $courseTable . '.title as label, SUM(' . $courseProgressTable . '.seconds) as value')
            ->join($lessonTable, $courseTable . '.id', '=', $lessonTable . '.course_id')
            ->join($topicTable, $lessonTable . '.id', '=', $topicTable . '.lesson_id')
            ->join($courseProgressTable, $topicTable . '.id', '=', $courseProgressTable . '.topic_id')
            ->groupBy($courseTable . '.id', $courseTable . '.title')
            ->orderBy('value', 'DESC')
            ->take($this->getLimit($limit));

        return $this->additionalConditions($query)
            ->get(['id', 'label', 'value'])
            ->map(function (Course $course) {
                $course->value = is_null($course->value) ? 0 : $course->value;
                return $course;
            })
            ->sortByDesc('value')
            ->values();
    }

    protected function additionalConditions(Builder $query): Builder
    {
        return $query;
    }
}
