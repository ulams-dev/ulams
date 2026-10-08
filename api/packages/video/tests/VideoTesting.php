<?php

namespace EscolaLms\Video\Tests;

use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Video\Models\Video;

trait VideoTesting
{
    public function createVideo()
    {
        $video = Video::factory()->create();
        Course::factory()
            ->has(Lesson::factory()
                ->has(Topic::factory()
                    ->state(fn() => [
                        'topicable_type' => \EscolaLms\TopicTypes\Models\TopicContent\Video::class,
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
                        'topicable_type' => \EscolaLms\TopicTypes\Models\TopicContent\Video::class,
                        'topicable_id' => Video::factory()->create()->getKey()
                    ])
                )
            )
            ->create();
    }
}
