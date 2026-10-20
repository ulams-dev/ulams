<?php

namespace Ulams\LearnerInsights\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\LearnerInsights\Services\StatusService;

/**
 * Re-evaluates one learner in one course. Unique per pair for the debounce window, so a burst of
 * signals costs one evaluation; the nightly sweep covers time-based rules.
 */
class EvaluateLearnerJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $userId, public readonly int $courseId)
    {
        $this->onConnection(config('learner_insights.queue_connection'));
        $this->onQueue(config('learner_insights.queue'));
    }

    public function uniqueId(): string
    {
        return $this->userId . ':' . $this->courseId;
    }

    public function uniqueFor(): int
    {
        return max(1, (int) config('learner_insights.evaluate_debounce_seconds'));
    }

    public function handle(StatusService $statuses): void
    {
        $statuses->evaluate($this->userId, $this->courseId);
    }

    public static function dispatchDebounced(int $userId, int $courseId): void
    {
        static::dispatch($userId, $courseId)->delay(now()->addSeconds((int) config('learner_insights.evaluate_debounce_seconds')));
    }
}
