<?php

namespace EscolaLms\CsvUsers\Services\Contracts;

use EscolaLms\Auth\Dtos\UserFilterCriteriaDto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface CsvUserServiceContract
{
    public function getDataToExport(UserFilterCriteriaDto $userFilterCriteriaDto): Collection;

    public function saveUserFromImport(Collection $data, string $returnUrl): Model;
}
