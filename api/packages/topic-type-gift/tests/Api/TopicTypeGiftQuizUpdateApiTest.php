<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use EscolaLms\TopicTypes\Events\TopicTypeChanged;
use Illuminate\Support\Facades\Event;

class TopicTypeGiftQuizUpdateApiTest extends TestCase
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

    public function testUpdateTopicGiftQuiz(): void
    {
        Event::fake([TopicTypeChanged::class]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/topics/' . $this->topic->getKey(), [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => GiftQuiz::class,
                'value' => 'lorem ipsum',
            ])
            ->assertOk();

        $data = $response->getData()->data;
        $value = $data->topicable->value;

        $this->topicId = $data->id;

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'value' => $value,
        ]);
        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }
}
