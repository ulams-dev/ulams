<?php

namespace EscolaLms\CourseAccess\Services\Contracts;

use EscolaLms\CourseAccess\Models\Course;

interface CourseAccessServiceContract
{
    public function addAccessForUsers(Course $course, array $users = []): void;
    public function addAccessForGroups(Course $course, array $groups = []): void;
    public function removeAccessForUsers(Course $course, array $users = []): void;
    public function removeAccessForGroups(Course $course, array $groups = []): void;
    public function setAccessForUsers(Course $course, array $users = []): void;
    public function setAccessForGroups(Course $course, array $groups = []): void;
    public function getUserCourseIds(int $userId): array;
}
