<?php

namespace Ulams\Entitlements\Events;

/**
 * `course_user` of one user and course was recomputed from the entitlements in force (granted, revoked or expired).
 * Ids only. Listeners derive access from it, like the course-variants package does for a family of variants.
 */
final class AccessProjected
{
    public function __construct(public readonly int $userId, public readonly int $courseId)
    {
    }
}
