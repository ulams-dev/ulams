<?php

namespace Ulams\CourseBuilder\Apply;

use Ulams\CourseBuilder\Contracts\RemovalPolicy;

/** Phase 2 behaviour: removed elements are deleted from the LMS. */
final class DeleteEverything implements RemovalPolicy
{
    public function shouldDelete(string $entityType, int $entityId): bool
    {
        return true;
    }
}
