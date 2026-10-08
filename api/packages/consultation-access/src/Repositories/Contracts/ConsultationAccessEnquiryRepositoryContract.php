<?php

namespace EscolaLms\ConsultationAccess\Repositories\Contracts;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConsultationAccessEnquiryRepositoryContract extends BaseRepositoryContract
{
    public function findByCriteria(array $criteria, int $perPage, ?OrderDto $orderDto = null): LengthAwarePaginator;

    public function findById(int $id): ConsultationAccessEnquiry;
}
