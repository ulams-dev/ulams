<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;

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
