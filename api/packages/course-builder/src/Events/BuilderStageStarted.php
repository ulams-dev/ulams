<?php

namespace Ulams\CourseBuilder\Events;

use Carbon\CarbonImmutable;

/**
 * A pipeline stage (a step name of the AG-UI log) or, with a null `stage`, the run itself has started.
 * Dispatched by `EventLog` so that other packages (authoring metrics, ADR 0194) can measure the
 * pipeline without the builder knowing about them. `at` is the timestamp of the AG-UI event row.
 */
final class BuilderStageStarted
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $runId,
        public readonly string $runKind,
        public readonly ?string $stage,
        public readonly CarbonImmutable $at,
    ) {
    }
}
