<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class GiftQuestionTestCase extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $this->seed(TopicTypeGiftPermissionSeeder::class);

        $this->topic = Topic::factory()
            ->for(Lesson::factory()
                ->for(Course::factory()->state(['status' => CourseStatusEnum::PUBLISHED])))
            ->create();

        $this->quiz = GiftQuiz::factory()->create();
        $this->topic->topicable()->associate($this->quiz)->save();

        $this->admin = $this->makeAdmin();
    }
}
