<?php

namespace Ulams\CourseAccess\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CourseAccessEnquiryRepositoryContract extends BaseRepositoryContract
{
    public function findByCriteria(array $criteria, int $perPage): LengthAwarePaginator;

    public function findByCourseIdAndUserId(int $courseId, int $userId): ?CourseAccessEnquiry;
}
