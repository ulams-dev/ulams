<?php

namespace Ulams\LearnerInsights\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\LearnerInsights\Models\Remediation;
use Ulams\LearnerInsights\Remediation\RemediationGenerator;

/** Generates one remediation off the request; unique per remediation so two struggles cost one call. */
class GenerateRemediationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $remediationId)
    {
        $this->onConnection(config('learner_insights.queue_connection'));
        $this->onQueue(config('learner_insights.queue'));
    }

    public function uniqueId(): string
    {
        return $this->remediationId;
    }

    public function handle(RemediationGenerator $generator): void
    {
        $remediation = Remediation::query()->find($this->remediationId);
        if ($remediation !== null && $remediation->status === Remediation::GENERATING) {
            $generator->generate($remediation);
        }
    }
}
