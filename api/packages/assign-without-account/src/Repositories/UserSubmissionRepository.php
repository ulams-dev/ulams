<?php

namespace EscolaLms\AssignWithoutAccount\Repositories;

use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\AssignWithoutAccount\Repositories\Contracts\UserSubmissionRepositoryContract;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\LikeCriterion;
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
