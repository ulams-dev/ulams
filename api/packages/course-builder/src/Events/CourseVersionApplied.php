<?php

namespace Ulams\CourseBuilder\Events;

use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;

/**
 * A version of a builder course reached learners (the course was applied): after generation, a chat change, a Living Course
 * update, a course-as-code import, drift absorption or a restore. Not sent for an apply that changed nothing, nor for variants.
 * The outgoing webhook `course.version_applied` is built from it, so a Git repository can follow the course (plan 8.4).
 */
final class CourseVersionApplied
{
    public function __construct(public readonly Session $session, public readonly Version $version, public readonly Release $release)
    {
    }
}
