<?php

namespace Ulams\AssignWithoutAccount\Repositories\Contracts;

use Ulams\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use Ulams\Core\Dtos\PaginationDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Core\Dtos\OrderDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserSubmissionRepositoryContract extends BaseRepositoryContract
{
    public function searchAndPaginateByCriteria(UserSubmissionSearchDto $searchDto, ?PaginationDto $paginationDto = null, ?OrderDto $orderDto = null): LengthAwarePaginator;
}
