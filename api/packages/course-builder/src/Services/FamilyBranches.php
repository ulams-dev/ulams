<?php

namespace Ulams\CourseBuilder\Services;

use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;

/**
 * Finds the applied blueprint of the branch an LMS course belongs to: the base (the session's course) or a variant
 * (ADR 0192). Used by the pieces the course-variants package asks the builder for: which topics are the same content in
 * two variants and which quiz questions can place a learner.
 */
final class FamilyBranches
{
    /** @return array{session:Session,variant:?Variant}|null null when the builder did not make the course */
    public function branchOf(int $courseId): ?array
    {
        $session = Session::query()->where('course_id', $courseId)->orderByDesc('updated_at')->first();
        if ($session !== null) {
            return ['session' => $session, 'variant' => null];
        }
        $variant = Variant::query()->where('course_id', $courseId)->first();

        return $variant !== null && $variant->session !== null ? ['session' => $variant->session, 'variant' => $variant] : null;
    }

    /** The document the branch's learners have (its applied version), or null before the first apply. */
    public function appliedDocument(array $branch): ?array
    {
        $id = $branch['variant']?->applied_version_id ?? ($branch['variant'] === null ? $branch['session']->applied_version_id : null);

        return $id === null ? null : Version::query()->where('session_id', $branch['session']->id)->find($id)?->document;
    }
}
