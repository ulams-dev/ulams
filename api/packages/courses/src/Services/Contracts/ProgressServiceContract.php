<?php


namespace Ulams\Courses\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\H5PUserProgress;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProgressServiceContract
{
    public function ping(User $user, Topic $topic): CourseProgressCollection;
    public function getByUser(User $user): Collection;
    public function getByUserPaginated(User $user, ?OrderDto $orderDto = null, ?int $perPage = 20, ?string $filter = null): LengthAwarePaginator;
    public function update(Course $course, User $user, array $progress): CourseProgressCollection;
    public function h5p(User $user, Topic $topic, string $event, $json): ?H5PUserProgress;
}
