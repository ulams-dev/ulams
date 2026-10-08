<?php

namespace Ulams\CsvUsers\Services\Contracts;

use Ulams\Auth\Dtos\UserFilterCriteriaDto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface CsvUserGroupServiceContract
{
    public function saveGroupFromImport(Collection $data): Model;
}
