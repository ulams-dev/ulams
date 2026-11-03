<?php

namespace Ulams\CourseVariants\Contracts;

use Ulams\Core\Models\User;

/** Tests the attribute conditions of a variant rule (the same conditions as the enrolment rules of the compliance package). */
interface ConditionMatcher
{
    /** @param list<array{attribute: string, operator: string, value?: mixed}> $conditions all must hold; none matches nobody */
    public function matches(User $user, array $conditions): bool;
}
