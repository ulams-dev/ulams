<?php

namespace Ulams\TopicTypeGift\Tests\Jobs;

use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Events\QuizAttemptJournalGradeReadyEvent;
use Ulams\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class MarkAttemptAsEndedJournalEventTest extends TestCase
{
    public function testEmitsJournalEventWhenQuizCountsToGradeAndFullyGraded(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);
        $attempt = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz->getKey()]);

        Event::fake([QuizAttemptFinishedEvent::class, QuizAttemptJournalGradeReadyEvent::class]);

        MarkAttemptAsEnded::dispatchSync($attempt->getKey());

        Event::assertDispatched(
            QuizAttemptJournalGradeReadyEvent::class,
            fn (QuizAttemptJournalGradeReadyEvent $event) => $event->getAttempt()->getKey() === $attempt->getKey()
        );
    }

    public function testDoesNotEmitJournalEventWhenQuizDoesNotCountToGrade(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => false]);
        $attempt = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz->getKey()]);

        Event::fake([QuizAttemptFinishedEvent::class, QuizAttemptJournalGradeReadyEvent::class]);

        MarkAttemptAsEnded::dispatchSync($attempt->getKey());

        Event::assertDispatched(QuizAttemptFinishedEvent::class);
        Event::assertNotDispatched(QuizAttemptJournalGradeReadyEvent::class);
    }

    public function testDoesNotEmitJournalEventWhenAnswerStillAwaitsGrading(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);
        $attempt = QuizAttempt::factory()->create(['topic_gift_quiz_id' => $quiz->getKey()]);
        AttemptAnswer::factory()->awaitingGrading()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
        ]);

        Event::fake([QuizAttemptFinishedEvent::class, QuizAttemptJournalGradeReadyEvent::class]);

        MarkAttemptAsEnded::dispatchSync($attempt->getKey());

        Event::assertDispatched(QuizAttemptFinishedEvent::class);
        Event::assertNotDispatched(QuizAttemptJournalGradeReadyEvent::class);
    }
}
