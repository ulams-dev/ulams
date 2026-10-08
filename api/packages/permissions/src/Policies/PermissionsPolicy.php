<?php

namespace EscolaLms\Permissions\Policies;

use EscolaLms\Core\Models\User;
use EscolaLms\Permissions\Enums\PermissionsPermissionsEnum;
use Illuminate\Auth\Access\HandlesAuthorization;
use EscolaLms\Permissions\Models\UserAdmin;

class PermissionsPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @return bool
     */
    public function administrate(UserAdmin $user): bool
    {
        return $user->can(PermissionsPermissionsEnum::PERMISSIONS_ROLE_MANAGE);
    }

    public function list(UserAdmin $user): bool
    {
        return $user->can(PermissionsPermissionsEnum::PERMISSIONS_ROLE_LIST);
    }

    public function read(UserAdmin $user): bool
    {
        return $user->can(PermissionsPermissionsEnum::PERMISSIONS_ROLE_READ);
    }

    public function create(UserAdmin $user): bool
    {
        return $user->can(PermissionsPermissionsEnum::PERMISSIONS_ROLE_CREATE);
    }

    public function update(UserAdmin $user): bool
    {
        return $user->can(PermissionsPermissionsEnum::PERMISSIONS_ROLE_UPDATE);
    }

    public function delete(UserAdmin $user): bool
    {
        return $user->can(PermissionsPermissionsEnum::PERMISSIONS_ROLE_DELETE);
    }
}
