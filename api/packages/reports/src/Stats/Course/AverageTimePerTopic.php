<?php

namespace Ulams\Reports\Stats\Course;

use Ulams\Courses\Models\Topic;
use Ulams\Reports\Stats\Topic\AverageTime as TopicAverageTime;
use Illuminate\Support\Collection;

class AverageTimePerTopic extends AbstractCourseStat
{
    public function calculate(): Collection
    {
        return $this->course->topics->mapWithKeys(fn(Topic $topic) => [
            $topic->id => [
                'average_time' => TopicAverageTime::make($topic)->calculate(),
                'title' => $topic->title,
            ]
        ]);
    }
}
