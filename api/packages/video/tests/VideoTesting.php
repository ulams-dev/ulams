<?php

namespace Ulams\Video\Tests;

use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Video\Models\Video;

trait VideoTesting
{
    public function createVideo()
    {
        $video = Video::factory()->create();
        Course::factory()
            ->has(Lesson::factory()
                ->has(Topic::factory()
                    ->state(fn() => [
                        'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                        'topicable_id' => $video->getKey()
                    ])
                )
            )
            ->create();

        return $video;
    }

    public function createCourse()
    {
        return Course::factory()
            ->state(['status' => CourseStatusEnum::PUBLISHED, 'public' => true])
            ->has(Lesson::factory()->state(['active' => true])
                ->has(Topic::factory()->state(['active' => true])
                    ->state(fn() => [
                        'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                        'topicable_id' => Video::factory()->create()->getKey()
                    ])
                )
            )
            ->create();
    }
}
