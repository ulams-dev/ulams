<?php

namespace EscolaLms\CourseAccess\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CourseAccessEnquiryRepositoryContract extends BaseRepositoryContract
{
    public function findByCriteria(array $criteria, int $perPage): LengthAwarePaginator;

    public function findByCourseIdAndUserId(int $courseId, int $userId): ?CourseAccessEnquiry;
}
