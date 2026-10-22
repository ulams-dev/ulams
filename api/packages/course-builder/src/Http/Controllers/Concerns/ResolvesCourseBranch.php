<?php

namespace Ulams\CourseBuilder\Http\Controllers\Concerns;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;

/**
 * A course id from the course-versions API names one branch of a builder session: the base course (the session's
 * `course_id`) or the course of a variant (ADR 0192). Anything else was not made with the builder.
 */
trait ResolvesCourseBranch
{
    /** @return array{0:Session,1:?Variant} */
    protected function branchOf(int $course, string $what = 'releases'): array
    {
        $session = Session::query()->where('course_id', $course)->orderByDesc('updated_at')->first();
        if ($session !== null) {
            return [$session, null];
        }
        $variant = Variant::query()->where('course_id', $course)->first();
        if ($variant === null || $variant->session === null) {
            throw new NotFoundHttpException("This course was not made with the Course Builder, so it has no {$what}. Adopt the course to track its versions.");
        }

        return [$variant->session, $variant];
    }
}
