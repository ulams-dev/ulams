<?php

namespace Ulams\CsvUsers\Services;

use Ulams\Auth\Repositories\Contracts\UserGroupRepositoryContract;
use Ulams\CsvUsers\Services\Contracts\CsvUserGroupServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CsvUserGroupService implements CsvUserGroupServiceContract
{
    private UserGroupRepositoryContract $groupRepository;

    public function __construct(UserGroupRepositoryContract $groupRepository)
    {
        $this->groupRepository = $groupRepository;
    }

    public function saveGroupFromImport(Collection $data): Model
    {
        return $this->groupRepository->create($data->toArray());
    }
}
