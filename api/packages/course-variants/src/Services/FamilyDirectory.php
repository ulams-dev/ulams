<?php

namespace Ulams\CourseVariants\Services;

use Ulams\Courses\Models\Course;
use Ulams\CourseVariants\Models\VariantGroup;

/**
 * Every family of the tenant, loaded once per request, for the catalogue: "Available for: Beginner, Expert" on the
 * default course's card, and the family of any variant course. Families are few (a tenant has tens), so one query for
 * groups and members, one for the course titles, is cheaper than a query per card.
 */
final class FamilyDirectory
{
    /** @var array<int,array>|null course id => family (default course and every selectable variant) */
    private ?array $byCourse = null;

    public function forget(): void
    {
        $this->byCourse = null;
    }

    /**
     * @return array{groupId:string,defaultCourseId:int,variants:array<int,array{courseId:int,label:string,level:?string,role:?string,isDefault:bool}>}|null
     */
    public function forCourse(int $courseId): ?array
    {
        return ($this->byCourse ??= $this->load())[$courseId] ?? null;
    }

    private function load(): array
    {
        $groups = VariantGroup::query()->with('members')->get();
        if ($groups->isEmpty()) {
            return [];
        }
        $ids = $groups->flatMap(fn (VariantGroup $g) => $g->courseIds())->unique()->all();
        $courses = Course::query()->whereIn('id', $ids)->get(['id', 'title', 'status', 'active_from', 'active_to'])->keyBy('id');
        $map = [];
        foreach ($groups as $group) {
            $variants = [];
            $default = $courses->get((int) $group->default_course_id);
            if ($default === null) {
                continue;
            }
            $variants[] = ['courseId' => (int) $group->default_course_id, 'label' => $group->default_label ?: (string) $default->title, 'level' => $group->default_level, 'role' => $group->default_role, 'isDefault' => true];
            foreach ($group->members as $m) {
                $course = $courses->get((int) $m->course_id);
                if ($m->retired_at === null && $course !== null && $course->is_published) {
                    $variants[] = ['courseId' => (int) $m->course_id, 'label' => $m->label, 'level' => $m->level, 'role' => $m->role, 'isDefault' => false];
                }
            }
            $family = ['groupId' => $group->id, 'defaultCourseId' => (int) $group->default_course_id, 'variants' => $variants];
            foreach ($group->courseIds() as $courseId) {
                $map[$courseId] = $family;
            }
        }

        return $map;
    }
}
