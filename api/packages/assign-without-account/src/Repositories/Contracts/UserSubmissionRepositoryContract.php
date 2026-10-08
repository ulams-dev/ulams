<?php

namespace EscolaLms\AssignWithoutAccount\Repositories\Contracts;

use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\Core\Dtos\OrderDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserSubmissionRepositoryContract extends BaseRepositoryContract
{
    public function searchAndPaginateByCriteria(UserSubmissionSearchDto $searchDto, ?PaginationDto $paginationDto = null, ?OrderDto $orderDto = null): LengthAwarePaginator;
}
