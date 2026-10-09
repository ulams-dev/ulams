<?php

namespace Ulams\TopicTypeGift\Tests\Api;

use Illuminate\Support\Facades\Config;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Support\Facades\Queue;
use Ulams\TopicTypeGift\Enum\AnswerKeyEnum;
use Ulams\TopicTypeGift\Enum\QuestionTypeEnum;
use Ulams\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\QuizAttempt;

/**
 * The attempt deadline must hold on every queue driver: `sync` runs a delayed job immediately,
 * which used to end the attempt the moment it was created.
 */
class QuizAttemptDeadlineQueueTest extends GiftQuestionTestCase
{
    use CreatesUsers;

    private function startAttempt(): QuizAttempt
    {
        $student = $this->makeStudent();
        $this->topic->course->users()->sync($student);

        $this->actingAs($student, 'api')
            ->postJson('api/quiz-attempts', ['topic_gift_quiz_id' => $this->quiz->getKey()])
            ->assertCreated()
            ->assertJsonFragment(['is_ended' => false]);

        return QuizAttempt::query()->where('user_id', $student->getKey())->firstOrFail();
    }

    public function testAttemptStaysOpenOnTheSyncDriver(): void
    {
        Config::set('queue.default', 'sync');

        $attempt = $this->startAttempt();

        $this->assertFalse($attempt->isEnded());
        $this->assertTrue($attempt->end_at->isFuture());
        $this->assertGreaterThan(0, now()->diffInMinutes($attempt->end_at));
    }

    public function testNoDelayedJobIsDispatchedOnDriversThatCannotDelay(): void
    {
        foreach (['sync', 'null'] as $connection) {
            Config::set('queue.default', $connection);
            Queue::fake();

            $this->startAttempt()->delete();

            Queue::assertNotPushed(MarkAttemptAsEnded::class);
        }
    }

    public function testDelayedJobIsDispatchedForDeadlineOnDatabaseAndRedis(): void
    {
        foreach (['database', 'redis'] as $connection) {
            Config::set('queue.default', $connection);
            Queue::fake();

            $attempt = $this->startAttempt();

            Queue::assertPushed(MarkAttemptAsEnded::class, function (MarkAttemptAsEnded $job) use ($attempt) {
                return $job->delay->format('Y-m-d H:i:s') === $attempt->end_at->format('Y-m-d H:i:s');
            });
            $attempt->delete();
        }
    }

    public function testExpiredAttemptIsRejectedOnSubmitWithoutAnyJob(): void
    {
        Config::set('queue.default', 'sync');
        $attempt = $this->startAttempt();
        $question = GiftQuestion::factory()->create(['topic_gift_quiz_id' => $this->quiz->getKey(), 'type' => QuestionTypeEnum::SHORT_ANSWERS, 'value' => 'Two plus two {=\\= four =\\= 4}']);

        // before the deadline the answer is accepted
        $payload = ['topic_gift_quiz_attempt_id' => $attempt->getKey(), 'topic_gift_question_id' => $question->getKey(), 'answer' => [AnswerKeyEnum::TEXT => '4']];
        $this->actingAs($attempt->user, 'api')->postJson('api/quiz-answers', $payload)->assertSuccessful();

        $attempt->update(['end_at' => now()->subMinute()]);

        $this->actingAs($attempt->user, 'api')->postJson('api/quiz-answers', $payload)->assertForbidden();
    }

    public function testDeadlineJobDoesNotEndAnAttemptBeforeItsDeadline(): void
    {
        $attempt = QuizAttempt::factory()->create(['end_at' => now()->addMinutes(30)]);

        MarkAttemptAsEnded::dispatchSync($attempt->getKey(), true);
        $this->assertFalse($attempt->refresh()->isEnded());

        // the explicit "end attempt" / submit path ends it at once
        MarkAttemptAsEnded::dispatchSync($attempt->getKey());
        $this->assertTrue($attempt->refresh()->isEnded());
    }
}
