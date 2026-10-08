<?php

namespace EscolaLms\ConsultationAccess\Repositories;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryRepositoryContract;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ConsultationAccessEnquiryRepository extends BaseRepository implements ConsultationAccessEnquiryRepositoryContract
{
    public function model(): string
    {
        return ConsultationAccessEnquiry::class;
    }

    public function getFieldsSearchable(): array
    {
        return [
            'consultation_id',
            'user_id',
            'status',
        ];
    }

    public function findByCriteria(array $criteria, int $perPage, ?OrderDto $orderDto = null): LengthAwarePaginator
    {
        $query = $this->queryWithAppliedCriteria($criteria);

        if (!is_null($orderDto)) {
            $query = $this->orderBy($query, $orderDto);
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ConsultationAccessEnquiry
    {
        /** @var ConsultationAccessEnquiry */
        return $this->model->newQuery()->findOrFail($id);
    }

    private function orderBy(Builder $query, ?OrderDto $orderDto): Builder
    {
        return match ($orderDto->getOrderBy()) {
            'term_date' => $query
                ->withAggregate('consultationUserTerm', 'executed_at')
                ->orderBy('consultation_user_term_executed_at', $orderDto->getOrder() ?? 'asc'),
            default => $query->orderBy($orderDto->getOrderBy() ?? 'id', $orderDto->getOrder() ?? 'asc'),
        };
    }
}
