<?php

namespace Ulams\Compliance\Services;

use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;

/**
 * Obligations on a course that has variants (ADR 0192, Q3): finishing any variant of the family fulfils the training,
 * unless an admin pinned one course, in which case only that course counts and the learner is given that course. A variant
 * course has no compliance settings of its own and uses the default course's. Without the course-variants package, or for
 * a course that is not in a family, every method answers as if the course stood alone.
 */
class FamilyCourses
{
    /** @var array<int,array{default:int,courses:int[],pinned:?int}|null> */
    private array $cache = [];

    /** @return array{default:int,courses:int[],pinned:?int}|null */
    private function family(int $courseId): ?array
    {
        if (array_key_exists($courseId, $this->cache)) {
            return $this->cache[$courseId];
        }
        $family = null;
        if (app()->bound(CourseVariantServiceContract::class)) {
            try {
                $group = app(CourseVariantServiceContract::class)->groupForCourse($courseId);
                $family = $group === null ? null : ['default' => (int) $group->default_course_id, 'courses' => $group->courseIds(), 'pinned' => $group->pinned_course_id !== null ? (int) $group->pinned_course_id : null];
            } catch (\Throwable) {
                $family = null; // the family tables are not there yet
            }
        }

        return $this->cache[$courseId] = $family;
    }

    /** The course whose compliance settings apply to `$courseId`: the family's default course for a variant, else itself. */
    public function policyCourse(int $courseId): int
    {
        return $this->family($courseId)['default'] ?? $courseId;
    }

    /**
     * The courses whose completion (or certificate) counts for an obligation on `$courseId`: the whole family, or only
     * the pinned course.
     *
     * @return int[]
     */
    public function counting(int $courseId): array
    {
        $family = $this->family($courseId);
        if ($family === null) {
            return [$courseId];
        }

        return $family['pinned'] !== null ? [$family['pinned']] : $family['courses'];
    }

    /**
     * The course an obligation on `$courseId` is given on: the pinned course of the family when there is one, else the
     * course itself.
     */
    public function assignedCourse(int $courseId): int
    {
        return $this->family($courseId)['pinned'] ?? $courseId;
    }

    /**
     * The courses an open obligation may be recorded on that finishing `$finishedCourseId` fulfils: the course itself, and
     * for a family every course of it unless a different course is pinned.
     *
     * @return int[]
     */
    public function fulfilledBy(int $finishedCourseId): array
    {
        $family = $this->family($finishedCourseId);
        if ($family === null) {
            return [$finishedCourseId];
        }

        return $family['pinned'] !== null && $family['pinned'] !== $finishedCourseId ? [$finishedCourseId] : $family['courses'];
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
