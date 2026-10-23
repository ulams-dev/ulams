<?php

namespace Ulams\Compliance\Services;

use Ulams\Compliance\Models\CourseSetting;

/** Per-course compliance settings with a request-local cache; a course without a row uses defaults. */
class CourseSettings
{
    /** @var array<int, CourseSetting|null> */
    private array $cache = [];

    public function __construct(private ?FamilyCourses $families = null)
    {
    }

    /** A variant course without a row of its own uses the settings of its family's default course (ADR 0192). */
    public function for(int $courseId): CourseSetting
    {
        $own = $this->row($courseId);
        if ($own !== null) {
            return $own;
        }
        $policy = $this->families?->policyCourse($courseId) ?? $courseId;

        return ($policy !== $courseId ? $this->row($policy) : null) ?? CourseSetting::defaults($courseId);
    }

    private function row(int $courseId): ?CourseSetting
    {
        if (!array_key_exists($courseId, $this->cache)) {
            $this->cache[$courseId] = CourseSetting::query()->where('course_id', $courseId)->first();
        }

        return $this->cache[$courseId];
    }

    public function has(int $courseId): bool
    {
        return $this->row($courseId) !== null;
    }

    public function save(int $courseId, array $values): CourseSetting
    {
        $setting = CourseSetting::query()->updateOrCreate(['course_id' => $courseId], $values);
        $this->cache[$courseId] = $setting;

        return $setting;
    }

    public function forget(): void
    {
        $this->cache = [];
        $this->families?->forget();
    }
}
