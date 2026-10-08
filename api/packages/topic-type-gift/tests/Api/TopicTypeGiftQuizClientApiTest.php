<?php

namespace Ulams\TopicTypeGift\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Tests\TestCase;

class TopicTypeGiftQuizClientApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = $this->makeAdmin();
        $this->topic = Topic::factory()
            ->for(Lesson::factory()
                ->for(Course::factory()))
            ->create();
    }

    public function testGetGiftQuizTopic(): void
    {
        $quiz = GiftQuiz::factory()->create();
        $this->topic->topicable()->associate($quiz)->save();

        $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/topics/' . $this->topic->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'topicable_type' => GiftQuiz::class,
            ]);
    }
}
