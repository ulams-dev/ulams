<?php

namespace EscolaLms\CourseAccess\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Repositories\Contracts\CourseAccessEnquiryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CourseAccessEnquiryRepository extends BaseRepository implements CourseAccessEnquiryRepositoryContract
{
    public function model(): string
    {
        return CourseAccessEnquiry::class;
    }

    public function getFieldsSearchable(): array
    {
        return [
            'course_id',
            'user_id',
            'status',
        ];
    }

    public function findByCriteria(array $criteria, int $perPage): LengthAwarePaginator
    {
        return $this->queryWithAppliedCriteria($criteria)
            ->paginate($perPage);
    }

    public function findByCourseIdAndUserId(int $courseId, int $userId): ?CourseAccessEnquiry
    {
        /** @var ?CourseAccessEnquiry */
        return $this->allQuery([
            'course_id' => $courseId,
            'user_id' => $userId,
        ])->first();
    }
}
