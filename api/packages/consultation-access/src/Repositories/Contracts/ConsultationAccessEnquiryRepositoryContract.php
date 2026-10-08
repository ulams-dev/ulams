<?php

namespace Ulams\ConsultationAccess\Repositories\Contracts;

use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConsultationAccessEnquiryRepositoryContract extends BaseRepositoryContract
{
    public function findByCriteria(array $criteria, int $perPage, ?OrderDto $orderDto = null): LengthAwarePaginator;

    public function findById(int $id): ConsultationAccessEnquiry;
}
