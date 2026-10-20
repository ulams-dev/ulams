<?php

namespace Ulams\Compliance\Services;

use Ulams\Compliance\Models\CourseSetting;

/** Per-course compliance settings with a request-local cache; a course without a row uses defaults. */
class CourseSettings
{
    /** @var array<int, CourseSetting|null> */
    private array $cache = [];

    public function for(int $courseId): CourseSetting
    {
        if (!array_key_exists($courseId, $this->cache)) {
            $this->cache[$courseId] = CourseSetting::query()->where('course_id', $courseId)->first();
        }

        return $this->cache[$courseId] ?? CourseSetting::defaults($courseId);
    }

    public function has(int $courseId): bool
    {
        $this->for($courseId);

        return $this->cache[$courseId] !== null;
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
    }
}
