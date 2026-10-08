<?php

namespace Ulams\PencilSpaces\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\PencilSpaces\Models\User;

interface UserRepositoryContract extends BaseRepositoryContract
{
    public function findById(int $id): User;
}
