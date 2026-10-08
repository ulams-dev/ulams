<?php

namespace Ulams\CsvUsers\Policies;

use Ulams\Core\Enums\UserRole;
use Ulams\CsvUsers\Enums\CsvUserPermissionsEnum;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class CsvUserGroupsPolicy
{
    use HandlesAuthorization;

    public function export(User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $user->can(CsvUserPermissionsEnum::CSV_USER_GROUP_EXPORT);
    }

    public function import(User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $user->can(CsvUserPermissionsEnum::CSV_USER_GROUP_IMPORT);
    }
}
