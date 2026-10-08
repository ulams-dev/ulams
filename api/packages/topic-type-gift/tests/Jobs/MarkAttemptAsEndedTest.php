<?php

namespace EscolaLms\TopicTypeGift\Tests\Jobs;

use EscolaLms\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use EscolaLms\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class MarkAttemptAsEndedTest extends TestCase
{
    public function testMarkQuizAttemptAsEnded(): void
    {
        Event::fake([QuizAttemptFinishedEvent::class]);

        $attempt = QuizAttempt::factory()->create();
        $this->assertFalse($attempt->refresh()->isEnded());

        MarkAttemptAsEnded::dispatch($attempt->getKey());

        $this->assertTrue($attempt->refresh()->isEnded());

        Event::assertDispatched(function (QuizAttemptFinishedEvent $event) use ($attempt) {
            $this->assertEquals($attempt->user_id, $event->getUser()->getKey());
            $this->assertEquals($attempt->getKey(), $event->getAttempt()->getKey());
            return true;
        });
    }

    public function testShouldNotReCloseQuizAttempt(): void
    {
        $endAt = Carbon::now()->subMinutes(5);

        $attempt = QuizAttempt::factory()->state([
            'end_at' => $endAt,
        ])
            ->create();

        MarkAttemptAsEnded::dispatch($attempt->getKey());
        $this->assertEquals($endAt->format('Y-m-d H:i'), $attempt->refresh()->end_at->format('Y-m-d H:i'));
    }
}
