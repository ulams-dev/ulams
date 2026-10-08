<?php

namespace Ulams\Lrs\Policies;

use Ulams\Auth\Models\User;
use Ulams\Lrs\Enums\LrsPermissionEnum;
use Illuminate\Auth\Access\HandlesAuthorization;

class StatementPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(LrsPermissionEnum::STATEMENT_LIST);
    }
}
