<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Carbon;

class AdminReadQuizAttemptApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
        $this->attempt = QuizAttempt::factory()->create();
    }

    public function testAdminQuizAttemptReadUnauthorized(): void
    {
        $this->getJson('api/admin/quiz-attempts/' . $this->attempt->getKey())
            ->assertUnauthorized();
    }

    public function testAdminQuizAttemptReadForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $this->attempt->getKey())
            ->assertForbidden();
    }

    public function testAdminQuizAttemptRead(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $this->attempt->getKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'topic_gift_quiz_id',
                    'started_at',
                    'end_at',
                    'max_score',
                    'min_pass_score',
                    'result_score',
                    'result_percent',
                    'is_passed',
                    'correct_answers_count',
                    'is_ended',
                    'answers',
                    'topic',
                    'course',
                ],
            ]);
    }

    public function testAdminQuizAttemptReadEndedResults(): void
    {
        $quiz = GiftQuiz::factory()->create(['min_pass_score' => 15]);
        $question1 = GiftQuestion::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'score' => 10]);
        $question2 = GiftQuestion::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'score' => 20]);

        $attempt = QuizAttempt::factory()->create([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => Carbon::now()->subMinutes(5),
        ]);

        // full score => correct
        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
            'topic_gift_question_id' => $question1->getKey(),
            'score' => 10,
        ]);
        // partial score => not correct
        AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
            'topic_gift_question_id' => $question2->getKey(),
            'score' => 10,
        ]);

        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data');

        $this->assertEquals(30, $data['max_score']);
        $this->assertEquals(20, $data['result_score']);
        $this->assertEquals(66.67, $data['result_percent']);
        $this->assertEquals(1, $data['correct_answers_count']);
        $this->assertTrue($data['is_passed']);
    }

    public function testAdminQuizAttemptReadNotEndedHidesResults(): void
    {
        $quiz = GiftQuiz::factory()->create(['min_pass_score' => 5]);
        $attempt = QuizAttempt::factory()->create([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => Carbon::now()->addMinutes(20),
        ]);

        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data');

        $this->assertFalse($data['is_ended']);
        $this->assertNull($data['result_score']);
        $this->assertNull($data['result_percent']);
        $this->assertNull($data['correct_answers_count']);
        $this->assertNull($data['is_passed']);
    }

    public function testAdminQuizAttemptReadIsPassedNullWithoutMinPassScore(): void
    {
        $quiz = GiftQuiz::factory()->create(['min_pass_score' => null]);
        $attempt = QuizAttempt::factory()->create([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => Carbon::now()->subMinutes(5),
        ]);

        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data');

        $this->assertNull($data['is_passed']);
    }
}
