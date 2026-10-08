<?php

namespace EscolaLms\CsvUsers\Services\Contracts;

use EscolaLms\Auth\Dtos\UserFilterCriteriaDto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface CsvUserGroupServiceContract
{
    public function saveGroupFromImport(Collection $data): Model;
}
