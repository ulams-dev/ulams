<?php

namespace Ulams\TopicTypeGift\Jobs;

use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Events\QuizAttemptJournalGradeReadyEvent;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Repositories\Contracts\QuizAttemptRepositoryContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MarkAttemptAsEnded implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $quizAttemptId;

    /**
     * @param bool $atDeadline true for the job scheduled for the attempt's deadline: it must never
     *                         end an attempt whose `end_at` is still in the future (a driver that
     *                         ignores the delay, such as `sync`, runs it immediately)
     */
    public function __construct(int $quizAttemptId, private readonly bool $atDeadline = false)
    {
        $this->quizAttemptId = $quizAttemptId;
    }

    /**
     * Whether the default queue connection honours `->delay()`. `sync` runs a delayed job at once
     * and `null` drops it, so for those the deadline is enforced on read/submit through `end_at`.
     */
    public static function queueCanDelay(): bool
    {
        $connection = config('queue.default');

        return !in_array(config("queue.connections.{$connection}.driver", $connection), ['sync', 'null'], true);
    }

    public function handle(QuizAttemptRepositoryContract $attemptRepository): void
    {
        /** @var ?QuizAttempt $result */
        $result = $attemptRepository->find($this->quizAttemptId);

        if (!$result || $result->isEnded()) {
            return;
        }

        if ($this->atDeadline && $result->end_at !== null && $result->end_at->isFuture()) {
            return;
        }

        $attemptRepository->update(['end_at' => Carbon::now()], $this->quizAttemptId);
        event(new QuizAttemptFinishedEvent($result->user, $result));

        // The journal grade is generated only once the whole attempt is graded.
        // Auto-scored quizzes are fully graded on finish; quizzes with open questions
        // wait for the lecturer (see AttemptAnswerService::adminUpdate).
        if ($result->giftQuiz->counts_to_grade && $result->isFullyGraded()) {
            event(new QuizAttemptJournalGradeReadyEvent($result->user, $result));
        }
    }
}
