<?php

namespace Ulams\CourseVariants\Tests\Support;

use Ulams\Core\Models\User;
use Ulams\CourseVariants\Contracts\ConditionMatcher;

/** Matches when the user's e-mail is one of the values of the first condition. */
final class FakeMatcher implements ConditionMatcher
{
    public function matches(User $user, array $conditions): bool
    {
        return $conditions !== [] && in_array($user->email, (array) ($conditions[0]['value'] ?? []), true);
    }
}
