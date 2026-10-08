<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class AdminListGiftQuizApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    public function testAdminListGiftQuizUnauthorized(): void
    {
        $this->getJson('api/admin/gift-quizes?course_id=1')
            ->assertUnauthorized();
    }

    public function testAdminListGiftQuizForbiddenForStudent(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/gift-quizes?course_id=1')
            ->assertForbidden();
    }

    public function testAdminListGiftQuizRequiresCourseId(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-quizes')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['course_id']);
    }

    public function testAdminListGiftQuizReturnsOnlyCourseQuizzes(): void
    {
        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();

        $lesson1 = Lesson::factory()->state(['course_id' => $course1->getKey()])->create();
        $lesson2 = Lesson::factory()->state(['course_id' => $course2->getKey()])->create();

        $quiz1 = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();
        $quiz3 = GiftQuiz::factory()->create();

        $topic1 = Topic::factory()->state(['lesson_id' => $lesson1->getKey(), 'title' => 'Quiz one'])->create();
        $topic2 = Topic::factory()->state(['lesson_id' => $lesson2->getKey(), 'title' => 'Quiz two'])->create();
        $topic3 = Topic::factory()->state(['lesson_id' => $lesson2->getKey(), 'title' => 'Quiz three'])->create();

        $topic1->topicable()->associate($quiz1)->save();
        $topic2->topicable()->associate($quiz2)->save();
        $topic3->topicable()->associate($quiz3)->save();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-quizes?course_id=' . $course1->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'topic_id'],
                ],
            ])
            ->assertJson([
                'data' => [
                    [
                        'id' => $quiz1->getKey(),
                        'title' => 'Quiz one',
                        'topic_id' => $topic1->getKey(),
                    ],
                ],
            ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-quizes?course_id=' . $course2->getKey())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testAdminListGiftQuizReturnsEmptyForCourseWithoutQuizzes(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-quizes?course_id=' . $course->getKey())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
