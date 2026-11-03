<?php

namespace Ulams\LearnerInsights\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;

/** Records one signal after commit, off the request (queue `LEARNER_INSIGHTS_QUEUE`). */
class RecordSignalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly array $signal)
    {
        $this->onConnection(config('learner_insights.queue_connection'));
        $this->onQueue(config('learner_insights.queue'));
        $this->afterCommit();
    }

    public function handle(SignalRecorder $recorder): void
    {
        $recorder->record(SignalData::fromArray($this->signal));
    }
}
