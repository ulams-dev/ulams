<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

class AttemptAnswerSaveAllApiTest extends TestCase
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

    public function testSaveAllAttemptAnswersUnauthorized(): void
    {
        $this->postJson('api/quiz-answers/all')
            ->assertUnauthorized();
    }

    public function testSaveAllAttemptAnswersForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/quiz-answers/all', [
                'topic_gift_quiz_attempt_id' => $this->attempt->getKey(),
            ])
            ->assertForbidden();
    }

    public function testSaveAllAttemptAnswers(): void
    {
        Bus::fake([MarkAttemptAsEnded::class]);

        $question = GiftQuestion::factory()
            ->state([
                'value' => 'Grant is buried in Grant\'s tomb.{FALSE}',
                'type' => QuestionTypeEnum::TRUE_FALSE,
                'score' => 3,
            ])
            ->create();

        $this->actingAs($this->student, 'api')
            ->postJson('api/quiz-answers/all', [
                'topic_gift_quiz_attempt_id' => $this->attempt->getKey(),
                'answers' => [[
                    'topic_gift_question_id' => $question->getKey(),
                    'answer' => [
                        AnswerKeyEnum::BOOL => false,
                    ],
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('topic_gift_attempt_answers', [
            'topic_gift_question_id' => $question->getKey(),
            'score' => 3,
        ]);

        Bus::assertDispatched(MarkAttemptAsEnded::class);
    }

    public function testSaveAllAttemptAnswersValidationFails(): void
    {
        Bus::fake([MarkAttemptAsEnded::class]);

        $question = GiftQuestion::factory()
            ->state([
                'value' => 'Grant is buried in Grant\'s tomb.{FALSE}',
                'type' => QuestionTypeEnum::TRUE_FALSE,
                'score' => 3,
            ])
            ->create();

        $this->actingAs($this->student, 'api')
            ->postJson('api/quiz-answers/all', [
                'topic_gift_quiz_attempt_id' => $this->attempt->getKey(),
                'answers' => [
                    [
                        'topic_gift_question_id' => $question->getKey(),
                        'answer' => [
                            'key' => 'value',
                        ],
                    ],
                    [
                        'topic_gift_question_id' => $question->getKey(),
                        'answer' => [
                            AnswerKeyEnum::BOOL => false,
                        ],
                    ],
                    [
                        'topic_gift_question_id' => $question->getKey(),
                        'answer' => [
                            'key' => 'value',
                        ],
                    ]
                ],
            ])
            ->assertJsonValidationErrors(['answers.0', 'answers.2'])
            ->assertUnprocessable();

        Bus::assertNotDispatched(MarkAttemptAsEnded::class);
    }
}
