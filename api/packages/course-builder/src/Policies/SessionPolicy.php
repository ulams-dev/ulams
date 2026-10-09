<?php

namespace Ulams\CourseBuilder\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Ulams\CourseBuilder\Enums\CourseBuilderPermissionsEnum;
use Ulams\CourseBuilder\Models\Session;

/**
 * Sessions are visible to their author and to tenant admins only. Every builder endpoint resolves
 * its session (directly or through a source, fragment, version or run) and checks this policy.
 */
class SessionPolicy
{
    public function use(Authenticatable $user): bool
    {
        return method_exists($user, 'can') && $user->can(CourseBuilderPermissionsEnum::COURSE_BUILDER_USE, 'api');
    }

    public function view(Authenticatable $user, Session $session): bool
    {
        if (!$this->use($user)) {
            return false;
        }

        return (int) $session->author_id === (int) $user->getAuthIdentifier()
            || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
    }

    /** Only the author drives the session (admins can look, not act). */
    public function update(Authenticatable $user, Session $session): bool
    {
        return $this->use($user) && (int) $session->author_id === (int) $user->getAuthIdentifier();
    }
}
