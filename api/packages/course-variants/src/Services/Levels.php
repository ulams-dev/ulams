<?php

namespace Ulams\CourseVariants\Services;

use Ulams\CourseVariants\Models\VariantGroup;

/** The level and role a course has inside its family, and which course is the closest one level up or down. */
final class Levels
{
    public const ORDER = ['beginner', 'intermediate', 'expert'];

    /** @return array{level:?string,role:?string} */
    public static function of(VariantGroup $group, int $courseId): array
    {
        if ((int) $group->default_course_id === $courseId) {
            return ['level' => $group->default_level, 'role' => $group->default_role];
        }
        $member = $group->members->firstWhere('course_id', $courseId);

        return ['level' => $member?->level, 'role' => $member?->role];
    }

    /** @return array<int,array{courseId:int,level:?string,role:?string,position:int}> default course first, then members in picker order */
    public static function all(VariantGroup $group): array
    {
        $rows = [['courseId' => (int) $group->default_course_id, 'level' => $group->default_level, 'role' => $group->default_role, 'position' => -1]];
        foreach ($group->members as $m) {
            $rows[] = ['courseId' => (int) $m->course_id, 'level' => $m->level, 'role' => $m->role, 'position' => (int) $m->position];
        }

        return $rows;
    }

    /**
     * The selectable course one level in `$direction` (-1 easier, +1 harder) from `$courseId` with the same role, or null.
     *
     * @param int[] $selectable
     */
    public static function neighbour(VariantGroup $group, int $courseId, int $direction, array $selectable): ?int
    {
        $from = self::of($group, $courseId);
        $index = array_search($from['level'] ?? 'intermediate', self::ORDER, true);
        if ($index === false) {
            return null;
        }
        $role = $from['role'];
        for ($i = $index + $direction; $i >= 0 && $i < count(self::ORDER); $i += $direction) {
            foreach (self::all($group) as $row) {
                if ($row['level'] === self::ORDER[$i] && $row['role'] === $role && in_array($row['courseId'], $selectable, true)) {
                    return $row['courseId'];
                }
            }
        }

        return null;
    }

    /** @param int[] $selectable the course of `$level` to suggest: the one with the learner's role when there is one @return ?int */
    public static function courseFor(VariantGroup $group, string $level, ?string $preferRole, array $selectable): ?int
    {
        $match = array_values(array_filter(self::all($group), fn (array $r) => $r['level'] === $level && in_array($r['courseId'], $selectable, true)));
        if ($match === []) {
            return null;
        }
        foreach ($match as $row) {
            if ($row['role'] === $preferRole) {
                return $row['courseId'];
            }
        }

        return $match[0]['courseId'];
    }
}
