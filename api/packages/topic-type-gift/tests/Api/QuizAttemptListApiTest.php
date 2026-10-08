<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

class QuizAttemptListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    public function testQuizAttemptListUnauthorized(): void
    {
        $this->getJson('api/quiz-attempts')
            ->assertUnauthorized();
    }

    public function testQuizAttemptListFilteringByQuizId(): void
    {
        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $quiz = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();

        QuizAttempt::factory()
            ->state(new Sequence(
                ['user_id' => $student->getKey(), 'topic_gift_quiz_id' => $quiz->getKey()],
                ['user_id' => $student->getKey(), 'topic_gift_quiz_id' => $quiz2->getKey()],
                ['user_id' => $student->getKey(), 'topic_gift_quiz_id' => $quiz2->getKey()],
                ['user_id' => $student2->getKey(), 'topic_gift_quiz_id' => $quiz2->getKey()],
            ))
            ->count(4)
            ->create();

        $this->actingAs($student, 'api')->getJson('api/quiz-attempts?topic_gift_quiz_id=' . $quiz->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($student, 'api')->getJson('api/quiz-attempts?topic_gift_quiz_id=' . $quiz2->getKey())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testQuizAttemptListFilteringByDate(): void
    {
        $student = $this->makeStudent();

        $quiz = GiftQuiz::factory()->create();
        $quiz2 = GiftQuiz::factory()->create();

        QuizAttempt::factory()
            ->state(new Sequence(
                [
                    'user_id' => $student->getKey(),
                    'topic_gift_quiz_id' => $quiz->getKey(),
                    'started_at' => now()->subDays(2),
                    'end_at' => now()->subDays(1),
                ],
                [
                    'user_id' => $student->getKey(),
                    'topic_gift_quiz_id' => $quiz2->getKey(),
                    'started_at' => now()->addDays(1),
                    'end_at' => now()->addDays(2),
                ],
            ))
            ->count(2)
            ->create();

        $response = $this->actingAs($student, 'api')->json('GET','api/quiz-attempts', [
            'date_from' => now()->format('Y-m-d'),
        ]);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'topic_gift_quiz_id' => $quiz2->getKey(),
        ]);

        $response = $this->actingAs($student, 'api')->json('GET','api/quiz-attempts', [
            'date_to' => now()->format('Y-m-d'),
        ]);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'topic_gift_quiz_id' => $quiz->getKey(),
        ]);
    }

    public function testQuizAttemptListPagination(): void
    {
        $student = $this->makeStudent();

        QuizAttempt::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(25)
            ->create();

        $this->actingAs($student, 'api')
            ->getJson('api/quiz-attempts?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 25,
                ],
            ]);

        $this->actingAs($student, 'api')
            ->getJson('api/quiz-attempts?per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 25,
                ],
            ]);
    }
}
