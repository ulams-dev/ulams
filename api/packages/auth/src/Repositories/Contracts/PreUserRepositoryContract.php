<?php

namespace Ulams\Auth\Repositories\Contracts;

use Ulams\Auth\Models\PreUser;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;

interface PreUserRepositoryContract extends BaseRepositoryContract
{
    public function findByToken(string $token): ?PreUser;
}
