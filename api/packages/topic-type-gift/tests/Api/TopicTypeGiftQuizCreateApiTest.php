<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class TopicTypeGiftQuizCreateApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
    }

    public function testCreateGiftQuiz(): void
    {
        $lesson = Lesson::factory()
            ->for(Course::factory())
            ->create();

        $this->response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics', [
                'title' => 'GiftQuiz',
                'lesson_id' => $lesson->getKey(),
                'topicable_type' => GiftQuiz::class,
                'max_attempts' => 2,
                'max_execution_time' => 10,
                'value' => 'lorem ipsum',
            ])
            ->assertCreated();

        $data = $this->response->getData()->data;
        $value = $data->topicable->value;

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'value' => $value,
            'max_attempts' => 2,
            'max_execution_time' => 10,
        ]);
    }

    public function testCreateGiftQuizDefaultsCountsToGradeToFalse(): void
    {
        $lesson = Lesson::factory()
            ->for(Course::factory())
            ->create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics', [
                'title' => 'GiftQuiz',
                'lesson_id' => $lesson->getKey(),
                'topicable_type' => GiftQuiz::class,
                'value' => 'lorem ipsum',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $response->getData()->data->topicable->id,
            'counts_to_grade' => false,
        ]);
    }

    public function testCreateGiftQuizWithCountsToGrade(): void
    {
        $lesson = Lesson::factory()
            ->for(Course::factory())
            ->create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics', [
                'title' => 'GiftQuiz',
                'lesson_id' => $lesson->getKey(),
                'topicable_type' => GiftQuiz::class,
                'value' => 'lorem ipsum',
                'counts_to_grade' => true,
            ])
            ->assertCreated()
            ->assertJsonFragment(['counts_to_grade' => true]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $response->getData()->data->topicable->id,
            'counts_to_grade' => true,
        ]);
    }

    public function testCreateGiftQuizDefaultsRandomizeOrderToFalse(): void
    {
        $lesson = Lesson::factory()
            ->for(Course::factory())
            ->create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics', [
                'title' => 'GiftQuiz',
                'lesson_id' => $lesson->getKey(),
                'topicable_type' => GiftQuiz::class,
                'value' => 'lorem ipsum',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $response->getData()->data->topicable->id,
            'randomize_order' => false,
        ]);
    }

    public function testCreateGiftQuizWithRandomizeOrder(): void
    {
        $lesson = Lesson::factory()
            ->for(Course::factory())
            ->create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics', [
                'title' => 'GiftQuiz',
                'lesson_id' => $lesson->getKey(),
                'topicable_type' => GiftQuiz::class,
                'value' => 'lorem ipsum',
                'randomize_order' => true,
            ])
            ->assertCreated()
            ->assertJsonFragment(['randomize_order' => true]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $response->getData()->data->topicable->id,
            'randomize_order' => true,
        ]);
    }
}
