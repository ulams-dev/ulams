<?php

namespace Ulams\TopicTypeGift\Tests\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Ulams\Auth\Models\User;
use Ulams\TopicTypeGift\Dtos\QuizAttemptDto;
use Ulams\TopicTypeGift\Exceptions\TooManyAttemptsException;
use Ulams\TopicTypeGift\Http\Resources\QuizAttemptResource;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptAllowanceContract;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Ulams\TopicTypeGift\Tests\TestCase;

/** ADR 0033: content updates never change a past result. */
class ProgressPreservationTest extends TestCase
{
    private function quiz(int $questions = 2): GiftQuiz
    {
        $quiz = GiftQuiz::factory()->create(['min_pass_score' => 1, 'max_attempts' => null]);
        for ($i = 0; $i < $questions; $i++) {
            GiftQuestion::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'score' => 5]);
        }

        return $quiz;
    }

    private function start(GiftQuiz $quiz, User $user): QuizAttempt
    {
        Event::fake();
        Queue::fake();

        return app(QuizAttemptServiceContract::class)->getActive(new QuizAttemptDto($quiz->getKey(), $user->getKey(), Carbon::now()));
    }

    private function finish(QuizAttempt $attempt): QuizAttempt
    {
        $attempt->forceFill(['end_at' => Carbon::now()->subMinute()])->save();

        return $attempt->refresh();
    }

    public function testTheMaximumScoreIsFrozenWhenAnAttemptStarts(): void
    {
        $user = User::factory()->create();
        $quiz = $this->quiz(2);
        $attempt = $this->start($quiz, $user);
        $this->assertSame(10.0, (float) $attempt->getRawOriginal('max_score'));
        $first = $quiz->questions->first();
        AttemptAnswer::factory()->create(['topic_gift_quiz_attempt_id' => $attempt->getKey(), 'topic_gift_question_id' => $first->getKey(), 'score' => 5]);
        $attempt = $this->finish($attempt);
        $this->assertSame(50.0, $attempt->result_percent);
        $this->assertTrue($attempt->is_passed);

        // an update adds a question and later removes another one
        GiftQuestion::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'score' => 10]);
        $quiz->refresh();
        app(GiftQuestionServiceContract::class)->archive($quiz->questions->last()->getKey());

        $attempt = $attempt->refresh();
        $this->assertSame(10, (int) $attempt->max_score);
        $this->assertSame(50.0, $attempt->result_percent, 'adding or removing questions does not change a past percentage');
        $this->assertSame(5, (int) $attempt->result_score);
    }

    public function testAttemptsWithoutASnapshotKeepTheLiveBehaviour(): void
    {
        $quiz = $this->quiz(2);
        $legacy = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'end_at' => Carbon::now()->subMinute()]);

        $this->assertNull($legacy->getRawOriginal('max_score'));
        $this->assertSame(10, (int) $legacy->max_score);
        GiftQuestion::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'score' => 10]);
        $this->assertSame(20, (int) $legacy->refresh()->load('giftQuiz')->max_score);

        $this->artisan('gift:snapshot-max-scores')->expectsOutputToContain('1 attempt(s) snapshotted')->assertSuccessful();
        GiftQuestion::factory()->create(['topic_gift_quiz_id' => $quiz->getKey(), 'score' => 10]);
        $this->assertSame(20, (int) $legacy->refresh()->load('giftQuiz')->max_score);
        $this->artisan('gift:snapshot-max-scores')->expectsOutputToContain('0 attempt(s) snapshotted')->assertSuccessful();
    }

    public function testArchivedQuestionsKeepTheirAnswersAndLeaveNewAttempts(): void
    {
        $user = User::factory()->create();
        $quiz = $this->quiz(2);
        $attempt = $this->start($quiz, $user);
        $archivedQuestion = $quiz->questions->first();
        $answer = AttemptAnswer::factory()->create(['topic_gift_quiz_attempt_id' => $attempt->getKey(), 'topic_gift_question_id' => $archivedQuestion->getKey(), 'score' => 5]);
        $attempt = $this->finish($attempt);

        app(GiftQuestionServiceContract::class)->archive($archivedQuestion->getKey());

        $this->assertNotNull($archivedQuestion->refresh()->archived_at);
        $this->assertTrue($archivedQuestion->isArchived());
        $this->assertSame(1, $quiz->refresh()->questions()->count());
        $this->assertSame(2, $quiz->allQuestions()->count());
        $this->assertNotNull(AttemptAnswer::query()->find($answer->getKey()), 'the answer stays');
        $this->assertSame(1, $attempt->refresh()->correct_answers_count, 'history still counts the archived question');

        $next = $this->start($quiz, User::factory()->create());
        $this->assertSame(5, (int) $next->getRawOriginal('max_score'));
        $this->assertSame(1, $next->giftQuiz->questions->count());

        // the history view of the old attempt still lists what the learner answered
        $resource = (new QuizAttemptResource($attempt->refresh()))->toArray(request());
        $this->assertContains($archivedQuestion->getKey(), $resource['questions']->pluck('id')->all());
        $fresh = (new QuizAttemptResource($next))->toArray(request());
        $this->assertNotContains($archivedQuestion->getKey(), $fresh['questions']->pluck('id')->all());
    }

    public function testDeletingAQuestionStillCascadesItsAnswersWhichIsWhyUpdatesArchiveInstead(): void
    {
        $attempt = QuizAttempt::factory()->create();
        $question = GiftQuestion::factory()->create(['topic_gift_quiz_id' => $attempt->topic_gift_quiz_id]);
        $answer = AttemptAnswer::factory()->create(['topic_gift_quiz_attempt_id' => $attempt->getKey(), 'topic_gift_question_id' => $question->getKey()]);

        app(GiftQuestionServiceContract::class)->delete($question->getKey());

        $this->assertNull(AttemptAnswer::query()->find($answer->getKey()));
    }

    public function testTheDefaultAllowanceGrantsNoExtraAttemptsAndAnotherOneCan(): void
    {
        $user = User::factory()->create();
        $quiz = $this->quiz(1);
        $quiz->forceFill(['max_attempts' => 1])->save();
        $first = $this->start($quiz, $user);
        $this->finish($first);

        $this->assertSame(0, app(QuizAttemptAllowanceContract::class)->extraAttempts($user->getKey(), $quiz->getKey()));
        try {
            $this->start($quiz, $user);
            $this->fail('The attempt limit was ignored.');
        } catch (TooManyAttemptsException) {
            $this->assertTrue(true);
        }

        $this->app->singleton(QuizAttemptAllowanceContract::class, fn () => new class implements QuizAttemptAllowanceContract {
            public function extraAttempts(int $userId, int $quizId): int
            {
                return 1;
            }
        });
        $this->app->forgetInstance(QuizAttemptServiceContract::class);
        $second = $this->start($quiz, $user);
        $this->assertNotSame($first->getKey(), $second->getKey());
        $this->finish($second);
        $this->app->forgetInstance(QuizAttemptServiceContract::class);
        $this->expectException(TooManyAttemptsException::class);
        $this->start($quiz, $user);
    }
}
