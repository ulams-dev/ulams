<?php

namespace EscolaLms\AssignWithoutAccount\Services\Contracts;

use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionDto;
use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use EscolaLms\Core\Dtos\PaginationDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserSubmissionServiceContract
{
    public function create(UserSubmissionDto $dto): UserSubmission;

    public function update(UserSubmissionDto $dto, int $id): UserSubmission;

    public function delete(int $id): bool;

    public function searchAndPaginate(UserSubmissionSearchDto $searchDto, ?PaginationDto $paginationDto): LengthAwarePaginator;
}
