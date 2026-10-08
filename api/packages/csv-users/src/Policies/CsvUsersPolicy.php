<?php

namespace EscolaLms\CsvUsers\Policies;

use Illuminate\Foundation\Auth\User;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\CsvUsers\Enums\CsvUserPermissionsEnum;
use Illuminate\Auth\Access\HandlesAuthorization;

class CsvUsersPolicy
{
    use HandlesAuthorization;

    public function export(User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        if ($user->can(CsvUserPermissionsEnum::CSV_USERS_EXPORT)) {
            return true;
        }

        return false;
    }

    public function import(User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        if ($user->can(CsvUserPermissionsEnum::CSV_USERS_IMPORT)) {
            return true;
        }

        return false;
    }
}
