<?php

namespace EscolaLms\PencilSpaces\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\PencilSpaces\Models\User;

interface UserRepositoryContract extends BaseRepositoryContract
{
    public function findById(int $id): User;
}
