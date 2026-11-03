<?php

namespace Ulams\CourseBuilder\CourseAsCode;

use Ulams\CourseBuilder\Contracts\PendingChanges;
use Ulams\CourseBuilder\Models\Session;

/** The default: nothing is waiting for a decision. */
final class NoPendingChanges implements PendingChanges
{
    public function forSession(Session $session): array
    {
        return [];
    }
}
