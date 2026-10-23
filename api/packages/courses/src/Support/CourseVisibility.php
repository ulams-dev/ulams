<?php

namespace Ulams\Courses\Support;

use Ulams\Courses\Models\Course;

/**
 * Extra reasons for a published course to be viewable by anyone although it is not listed in the catalogue
 * (`findable` is off). A family of course variants uses it: the variants are hidden from the catalogue, shown once as
 * their default course, and still open on their own page and programme (ADR 0192). Packages register a callback; a
 * course is viewable when any callback says so. The courses package knows nothing about who registers.
 */
final class CourseVisibility
{
    /** @var list<callable(Course): bool> */
    private static array $viewable = [];

    /** @param callable(Course): bool $callback true when the (published) course may be viewed without being listed */
    public static function viewableWhen(callable $callback): void
    {
        self::$viewable[] = $callback;
    }

    public static function viewable(Course $course): bool
    {
        foreach (self::$viewable as $callback) {
            if ($callback($course)) {
                return true;
            }
        }

        return false;
    }
}
