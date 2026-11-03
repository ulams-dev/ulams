<?php

namespace Ulams\CourseBuilder\Contracts;

use Ulams\CourseBuilder\Models\Session;

/**
 * Changes of a course that are proposed but not decided yet, for the guard of a course-as-code push (plan 9.5): a push that edits an
 * element an open update proposal also edits would turn that proposal item into a conflict. Living Course knows its proposals; the
 * builder only asks. The default knows nothing, so a push is never held back on an install without Living Course.
 */
interface PendingChanges
{
    /**
     * @return array<int,array{id:string,number:int,elementIds:string[]}> open proposals with the ids of the elements they would change
     */
    public function forSession(Session $session): array;
}
