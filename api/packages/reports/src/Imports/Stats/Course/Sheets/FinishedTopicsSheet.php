<?php

namespace EscolaLms\Reports\Imports\Stats\Course\Sheets;

use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\Course\Strategies\TopicTitleStrategyContext;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

abstract class FinishedTopicsSheet implements OnEachRow, WithHeadingRow
{
    protected Course $course;
    protected array $topics = [];

    public function __construct(Course $course)
    {
        $this->course = $course;
        $this->prepareTopics();
    }

    public function onRow(Row $row)
    {
        $user = User::query()->where('email', '=', $row['Email'])->first();
        if ($user) {
            foreach ($this->topics as $title => $topic) {
                $this->processRow($user, $topic, $row[$title]);
            }
        }
    }

    protected function processRow(User $user, Topic $topic, $value): CourseProgress
    {
        $coursesProgress = CourseProgress::query()
            ->where('user_id', '=', $user->getKey())
            ->where('topic_id', '=', $topic->getKey())
            ->first();
        if ($coursesProgress) {
            $coursesProgress->update($this->prepareUpdateData($value, $coursesProgress));
        } else {
            $coursesProgress = CourseProgress::create(array_merge([
                'user_id' => $user->getKey(),
                'topic_id' => $topic->getKey(),
            ], $this->prepareUpdateData($value)));
        }

        return $coursesProgress;
    }

    protected abstract function prepareUpdateData($value, CourseProgress $courseProgress = null): array;

    protected function prepareTopics()
    {
        $this->course->topics->each(function ($topic) {
            $this->topics[(new TopicTitleStrategyContext($topic))->getStrategy()->makeTitle()] = $topic;
        });
    }
}
