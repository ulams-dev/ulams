<?php

namespace Ulams\TopicTypeGift\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Tests\TestCase;

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
