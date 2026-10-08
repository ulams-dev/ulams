<?php

namespace Ulams\AssignWithoutAccount\Services\Contracts;

use Ulams\AssignWithoutAccount\Dto\UserSubmissionDto;
use Ulams\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use Ulams\AssignWithoutAccount\Models\UserSubmission;
use Ulams\Core\Dtos\PaginationDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserSubmissionServiceContract
{
    public function create(UserSubmissionDto $dto): UserSubmission;

    public function update(UserSubmissionDto $dto, int $id): UserSubmission;

    public function delete(int $id): bool;

    public function searchAndPaginate(UserSubmissionSearchDto $searchDto, ?PaginationDto $paginationDto): LengthAwarePaginator;
}
