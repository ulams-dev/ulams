<?php

namespace Ulams\AssignWithoutAccount\Repositories;

use Ulams\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use Ulams\AssignWithoutAccount\Models\UserSubmission;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Dtos\PaginationDto;
use Ulams\Core\Repositories\BaseRepository;
use Ulams\AssignWithoutAccount\Repositories\Contracts\UserSubmissionRepositoryContract;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\Core\Repositories\Criteria\Primitives\LikeCriterion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserSubmissionRepository extends BaseRepository implements UserSubmissionRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return UserSubmission::class;
    }

    public function searchAndPaginateByCriteria(UserSubmissionSearchDto $searchDto, ?PaginationDto $paginationDto = null, ?OrderDto $orderDto = null): LengthAwarePaginator
    {
        $criteria = $this->makeCriteria($searchDto);

        $query = $this->model->newQuery();
        $query = $this->applyCriteria($query, $criteria);
        $query->orderBy($orderDto?->getOrderBy() ?? 'id', $orderDto?->getOrder() ?? 'asc');

        return $query->paginate($paginationDto->getLimit());
    }

    private function makeCriteria(UserSubmissionSearchDto $searchDto): array
    {
        $criteria = [];

        if ($searchDto->getMorphableId() && $searchDto->getMorphableType()) {
            $criteria[] = new EqualCriterion('morphable_id', $searchDto->getMorphableId());
            $criteria[] = new EqualCriterion('morphable_type', $searchDto->getMorphableType());
        }

        if ($searchDto->getMorphableType()) {
            $criteria[] = new EqualCriterion('morphable_type', $searchDto->getMorphableType());
        }

        if ($searchDto->getEmail()) {
            $criteria[] = new LikeCriterion('email', $searchDto->getEmail());
        }

        if ($searchDto->getStatus()) {
            $criteria[] = new EqualCriterion('status', $searchDto->getStatus());
        }

        return $criteria;
    }
}
