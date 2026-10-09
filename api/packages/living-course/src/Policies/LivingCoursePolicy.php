<?php

namespace Ulams\LivingCourse\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Policies\SessionPolicy;
use Ulams\LivingCourse\Enums\LivingCoursePermissionsEnum;

/**
 * Living Course data belongs to a builder session: it is visible to the session author and to
 * tenant admins (ADR 0027). Acting (deciding proposals, managing connections) needs the author or
 * the permission `living_course_review` (ADR 0030, decision 10), and always `course_builder_use`.
 */
class LivingCoursePolicy
{
    public function view(Authenticatable $user, Session $session): bool
    {
        return (new SessionPolicy())->view($user, $session);
    }

    public function act(Authenticatable $user, Session $session): bool
    {
        $sessions = new SessionPolicy();
        if ($sessions->update($user, $session)) {
            return true;
        }

        return $sessions->use($user)
            && method_exists($user, 'can')
            && $user->can(LivingCoursePermissionsEnum::LIVING_COURSE_REVIEW, 'api');
    }
}
