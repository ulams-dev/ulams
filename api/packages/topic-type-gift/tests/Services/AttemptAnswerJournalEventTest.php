<?php

namespace EscolaLms\TopicTypeGift\Tests\Services;

use EscolaLms\TopicTypeGift\Dtos\AdminUpdateAttemptAnswerDto;
use EscolaLms\TopicTypeGift\Events\QuizAttemptJournalGradeReadyEvent;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class AttemptAnswerJournalEventTest extends TestCase
{
    private AttemptAnswerServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttemptAnswerServiceContract::class);
    }

    private function endedAttempt(bool $countsToGrade): QuizAttempt
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => $countsToGrade]);

        return QuizAttempt::factory()->create([
            'topic_gift_quiz_id' => $quiz->getKey(),
            'end_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    public function testGradingLastAwaitingAnswerEmitsJournalEvent(): void
    {
        $attempt = $this->endedAttempt(true);
        $answer = AttemptAnswer::factory()->awaitingGrading()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
        ]);

        Event::fake([QuizAttemptJournalGradeReadyEvent::class]);

        $this->service->adminUpdate($answer->getKey(), new AdminUpdateAttemptAnswerDto(8.0, 'good'));

        $this->assertNotNull($answer->refresh()->graded_at);
        Event::assertDispatched(
            QuizAttemptJournalGradeReadyEvent::class,
            fn (QuizAttemptJournalGradeReadyEvent $event) => $event->getAttempt()->getKey() === $attempt->getKey()
        );
    }

    public function testDoesNotEmitWhileAnotherAnswerStillAwaitsGrading(): void
    {
        $attempt = $this->endedAttempt(true);
        $first = AttemptAnswer::factory()->awaitingGrading()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
        ]);
        $second = AttemptAnswer::factory()->awaitingGrading()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
        ]);

        Event::fake([QuizAttemptJournalGradeReadyEvent::class]);

        $this->service->adminUpdate($first->getKey(), new AdminUpdateAttemptAnswerDto(5.0, null));
        Event::assertNotDispatched(QuizAttemptJournalGradeReadyEvent::class);

        $this->service->adminUpdate($second->getKey(), new AdminUpdateAttemptAnswerDto(5.0, null));
        Event::assertDispatched(QuizAttemptJournalGradeReadyEvent::class);
    }

    public function testDoesNotEmitWhenQuizDoesNotCountToGrade(): void
    {
        $attempt = $this->endedAttempt(false);
        $answer = AttemptAnswer::factory()->awaitingGrading()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
        ]);

        Event::fake([QuizAttemptJournalGradeReadyEvent::class]);

        $this->service->adminUpdate($answer->getKey(), new AdminUpdateAttemptAnswerDto(8.0, null));

        Event::assertNotDispatched(QuizAttemptJournalGradeReadyEvent::class);
    }

    public function testReGradingAlreadyGradedAttemptEmitsAgain(): void
    {
        $attempt = $this->endedAttempt(true);
        $answer = AttemptAnswer::factory()->create([
            'topic_gift_quiz_attempt_id' => $attempt->getKey(),
        ]);

        Event::fake([QuizAttemptJournalGradeReadyEvent::class]);

        $this->service->adminUpdate($answer->getKey(), new AdminUpdateAttemptAnswerDto(10.0, 'revised'));

        Event::assertDispatched(QuizAttemptJournalGradeReadyEvent::class);
    }
}
