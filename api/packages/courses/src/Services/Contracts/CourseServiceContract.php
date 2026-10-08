<?php


namespace Ulams\Courses\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Courses\Models\Course;
use Illuminate\Database\Eloquent\Builder;

interface CourseServiceContract
{
    public function getCoursesListWithOrdering(OrderDto $orderDto, array $search = []): Builder;
    public function getScormPlayer(int $courseId);
    public function sort($class, $orders): void;
    public function activatePublishedCourses(): void;
}
