<?php

namespace Ulams\CourseBuilder\Events;

use Carbon\CarbonImmutable;

/**
 * A pipeline stage, or with a null `stage` the whole run, has ended. `outcome` is `finished`, or
 * `error` for a failed or cancelled run (RUN_ERROR); a stage always reports `finished`.
 */
final class BuilderStageFinished
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $runId,
        public readonly string $runKind,
        public readonly ?string $stage,
        public readonly CarbonImmutable $at,
        public readonly string $outcome = 'finished',
    ) {
    }
}
