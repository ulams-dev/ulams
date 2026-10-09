<?php

namespace Ulams\Lti\Platform;

use Illuminate\Contracts\Auth\Authenticatable;
use Ulams\Lti\Support\Lti;

/**
 * ulams roles to LTI roles (platform side) and back (tool side).
 */
class RoleMapper
{
    /**
     * @return string[]
     */
    public function ltiRoles(Authenticatable $user): array
    {
        $has = fn (string $role) => method_exists($user, 'hasRole') && $user->hasRole($role);

        $roles = [];
        if ($has('admin')) {
            $roles[] = Lti::ROLE_ADMINISTRATOR;
            $roles[] = Lti::ROLE_INSTRUCTOR;
        } elseif ($has('tutor')) {
            $roles[] = Lti::ROLE_INSTRUCTOR;
        }
        if ($roles === []) {
            $roles[] = Lti::ROLE_LEARNER;
        }

        return $roles;
    }

    /**
     * Tool side. Instructors become tutors; nobody becomes an administrator through LTI.
     *
     * @param string[] $ltiRoles
     */
    public function ulamsRole(array $ltiRoles): string
    {
        foreach ($ltiRoles as $role) {
            if (str_ends_with($role, '#Instructor') || str_ends_with($role, '#ContentDeveloper') || str_ends_with($role, '#TeachingAssistant')) {
                return 'tutor';
            }
        }

        return 'student';
    }
}
