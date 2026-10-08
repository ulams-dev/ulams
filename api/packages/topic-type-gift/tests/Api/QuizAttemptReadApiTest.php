<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Strategies\GiftQuestionStrategyFactory;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Carbon;

class QuizAttemptReadApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);

        $this->student = $this->makeStudent();
        $this->attempt = QuizAttempt::factory()
            ->state(['user_id' => $this->student->getKey()])
            ->create();
    }

    public function testQuizAttemptReadUnauthorized(): void
    {
        $this->getJson('api/quiz-attempts/' . $this->attempt->getKey())
            ->assertUnauthorized();
    }

    public function testQuizAttemptReadForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/quiz-attempts/' . $this->attempt->getKey())
            ->assertForbidden();
    }

    public function testQuizAttemptRead(): void
    {
        $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $this->attempt->getKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'topic_gift_quiz_id',
                    'started_at',
                    'end_at',
                    'max_score',
                    'result_score',
                    'answers',
                ],
            ]);
    }

    public function testQuizAttemptReadAnswers(): void
    {
        $quiz = GiftQuiz::factory()->create();

        $question1 = GiftQuestion::factory()->state(['topic_gift_quiz_id' => $quiz->getKey()])->create();
        $question2 = GiftQuestion::factory()->state(['topic_gift_quiz_id' => $quiz->getKey()])->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => Carbon::now()->subMinutes(),
        ])
            ->create();

        $answer1 = AttemptAnswer::factory()
            ->state(['topic_gift_question_id' => $question1->getKey(), 'topic_gift_quiz_attempt_id' => $attempt->getKey()])
            ->create();

        $answer2 = AttemptAnswer::factory()
            ->state(['topic_gift_question_id' => $question2->getKey(), 'topic_gift_quiz_attempt_id' => $attempt->getKey()])
            ->create();

        $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'max_score' => $question1->score + $question2->score,
                'result_score' => $answer1->score + $answer2->score,
            ])
            ->assertJsonCount(2, 'data.answers')
            ->assertJsonFragment([
                'topic_gift_question_id' => $answer1->topic_gift_question_id,
                'score' => $answer1->score,
                'answer' => $answer1->answer,
                'feedback' => $answer1->feedback,
            ])
            ->assertJsonFragment([
                'topic_gift_question_id' => $answer2->topic_gift_question_id,
                'score' => $answer2->score,
                'answer' => $answer2->answer,
                'feedback' => $answer2->feedback,
            ]);
    }

    public function testQuizAttemptShouldNotReturnResultWhenAttemptIsActive(): void
    {
        $quiz = GiftQuiz::factory()->create();
        $question1 = GiftQuestion::factory()->state(['topic_gift_quiz_id' => $quiz->getKey()])->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])
            ->create();

        AttemptAnswer::factory()
            ->state(['topic_gift_question_id' => $question1->getKey(), 'topic_gift_quiz_attempt_id' => $attempt->getKey()])
            ->create();

        $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->assertJsonFragment(['result_score' => null])
            ->assertJsonCount(1, 'data.answers');
    }

    public function testQuizAttemptQuestionsKeepOrderWhenRandomizeDisabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => false]);

        // Insertion order (by id) deliberately differs from the configured `order`.
        $first = GiftQuestion::factory()->state(['topic_gift_quiz_id' => $quiz->getKey(), 'order' => 3])->create();
        $second = GiftQuestion::factory()->state(['topic_gift_quiz_id' => $quiz->getKey(), 'order' => 1])->create();
        $third = GiftQuestion::factory()->state(['topic_gift_quiz_id' => $quiz->getKey(), 'order' => 2])->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])->create();

        $ids = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->assertJsonCount(3, 'data.questions')
            ->json('data.questions.*.id');

        $this->assertSame([$second->getKey(), $third->getKey(), $first->getKey()], $ids);
    }

    public function testQuizAttemptQuestionsAreShuffledWhenRandomizeEnabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);
        $questions = GiftQuestion::factory()->count(6)
            ->state(['topic_gift_quiz_id' => $quiz->getKey()])
            ->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])->create();

        $ids = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->assertJsonCount(6, 'data.questions')
            ->json('data.questions.*.id');

        // Every question is returned exactly once, regardless of the order.
        $this->assertEqualsCanonicalizing($questions->pluck('id')->all(), $ids);
    }

    public function testQuizAttemptRandomOrderIsStableAcrossRequests(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);
        GiftQuestion::factory()->count(8)
            ->state(['topic_gift_quiz_id' => $quiz->getKey()])
            ->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])->create();

        $firstRequest = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.*.id');

        $secondRequest = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.*.id');

        // Refreshing the attempt must not change the order.
        $this->assertSame($firstRequest, $secondRequest);
    }

    public function testQuizAttemptRandomOrderIsSeededByAttemptId(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);
        GiftQuestion::factory()->count(8)
            ->state(['topic_gift_quiz_id' => $quiz->getKey()])
            ->create();

        $orders = [];

        foreach (range(1, 3) as $ignored) {
            $attempt = QuizAttempt::factory()->state([
                'user_id' => $this->student->getKey(),
                'topic_gift_quiz_id' => $quiz->getKey(),
            ])->create();

            $ids = $this->actingAs($this->student, 'api')
                ->getJson('api/quiz-attempts/' . $attempt->getKey())
                ->assertOk()
                ->json('data.questions.*.id');

            // The order is exactly the questions shuffled with the attempt id as seed.
            $expected = $quiz->questions()->get()
                ->sortBy('id')
                ->values()
                ->shuffle($attempt->getKey())
                ->pluck('id')
                ->all();

            $this->assertSame($expected, $ids);

            $orders[] = $ids;
        }

        // Different attempts of the same student may get a different order.
        $this->assertGreaterThan(1, count(array_unique($orders, SORT_REGULAR)));
    }

    public function testQuizAttemptQuestionOptionsKeepSourceOrderWhenRandomizeDisabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => false]);
        $question = GiftQuestion::factory()->state([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'value' => 'Pick one {~a ~b ~c ~d =e}',
        ])->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])->create();

        $options = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.0.options.answers');

        // Not randomized -> the options keep their order from the GIFT definition.
        $expected = GiftQuestionStrategyFactory::create($question)->getOptions()['answers'];
        $this->assertSame($expected, $options);
    }

    public function testQuizAttemptQuestionOptionsAreShuffledAndStableWhenRandomizeEnabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);
        $question = GiftQuestion::factory()->state([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'value' => 'Pick one {~a ~b ~c ~d =e}',
        ])->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])->create();

        $first = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.0.options.answers');

        $second = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.0.options.answers');

        // Same set of options, stable across refreshes, and seeded by the attempt id.
        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd', 'e'], $first);
        $this->assertSame($first, $second);

        $expected = GiftQuestionStrategyFactory::create($question, $attempt->getKey())->getOptions()['answers'];
        $this->assertSame($expected, $first);
    }

    public function testQuizAttemptMatchingOptionsAreStableWhenRandomizeEnabled(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);
        $question = GiftQuestion::factory()->state([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'type' => QuestionTypeEnum::MATCHING,
            'value' => 'Match {=Canada -> Ottawa =Italy -> Rome =Japan -> Tokyo =India -> New Delhi}',
        ])->create();

        $attempt = QuizAttempt::factory()->state([
            'user_id' => $this->student->getKey(),
            'topic_gift_quiz_id' => $quiz->getKey(),
        ])->create();

        $first = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.0.options');

        $second = $this->actingAs($this->student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->json('data.questions.0.options');

        // Matching columns stay a full permutation, stable across refreshes and seeded.
        $this->assertEqualsCanonicalizing(['Canada', 'Italy', 'Japan', 'India'], $first['sub_questions']);
        $this->assertEqualsCanonicalizing(['Ottawa', 'Rome', 'Tokyo', 'New Delhi'], $first['sub_answers']);
        $this->assertSame($first, $second);

        $expected = GiftQuestionStrategyFactory::create($question, $attempt->getKey())->getOptions();
        $this->assertSame($expected, $first);
    }
}
