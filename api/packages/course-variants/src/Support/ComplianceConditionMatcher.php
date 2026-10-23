<?php

namespace Ulams\CourseVariants\Support;

use Ulams\Compliance\Services\RuleEngine;
use Ulams\Core\Models\User;
use Ulams\CourseVariants\Contracts\ConditionMatcher;

/** Uses the compliance rule engine's operators and attributes (group, user columns, custom user fields). */
final class ComplianceConditionMatcher implements ConditionMatcher
{
    public function matches(User $user, array $conditions): bool
    {
        return class_exists(RuleEngine::class) && app(RuleEngine::class)->holds($user, $conditions);
    }
}
