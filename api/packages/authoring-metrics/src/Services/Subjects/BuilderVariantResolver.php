<?php

namespace Ulams\AuthoringMetrics\Services\Subjects;

use Ulams\AuthoringMetrics\Contracts\SubjectCourseResolver;
use Ulams\AuthoringMetrics\Services\SubjectResolvers;
use Ulams\CourseBuilder\Models\Variant;

/** Calls that write a variant of the course: variant, session, course. */
class BuilderVariantResolver implements SubjectCourseResolver
{
    public function subjectType(): string
    {
        return Variant::SUBJECT_TYPE;
    }

    public function bucket(): string
    {
        return SubjectResolvers::BUILD;
    }

    public function subjectIds(array $sessionIds): array
    {
        return $sessionIds === [] ? [] : Variant::query()->whereIn('session_id', $sessionIds)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
}
