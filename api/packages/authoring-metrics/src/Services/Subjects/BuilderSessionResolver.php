<?php

namespace Ulams\AuthoringMetrics\Services\Subjects;

use Ulams\AuthoringMetrics\Contracts\SubjectCourseResolver;
use Ulams\AuthoringMetrics\Services\SubjectResolvers;
use Ulams\CourseBuilder\Models\Session;

/** Calls of the build pipeline and of element chat edits: the subject is the session itself. */
class BuilderSessionResolver implements SubjectCourseResolver
{
    public function subjectType(): string
    {
        return Session::SUBJECT_TYPE;
    }

    public function bucket(): string
    {
        return SubjectResolvers::BUILD;
    }

    public function subjectIds(array $sessionIds): array
    {
        return array_values($sessionIds);
    }
}
