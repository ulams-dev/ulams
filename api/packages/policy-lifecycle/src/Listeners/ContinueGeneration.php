<?php

namespace Ulams\PolicyLifecycle\Listeners;

use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\PolicyLifecycle\Services\VariantGenerator;

/** Moves a department's builder session along when one of its runs ends (see VariantGenerator). */
final class ContinueGeneration
{
    public function __construct(private readonly VariantGenerator $generator)
    {
    }

    public function handle(BuilderStageFinished $event): void
    {
        if ($event->stage !== null || !in_array($event->runKind, ['ingest', 'interview', 'outline'], true)) {
            return;
        }
        try {
            $this->generator->onRunEnded($event->sessionId, $event->runId, $event->runKind, $event->outcome === 'finished');
        } catch (\Throwable $e) {
            // a failure here must never fail the builder run that has just ended; the owner can start the department again
            report($e);
        }
    }
}
