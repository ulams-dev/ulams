<?php

namespace Ulams\H5P\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Ulams\Core\Models\User;
use Ulams\H5P\Enums\H5PPermissionsEnum;

class H5PContentPolicy
{
    use HandlesAuthorization;

    public function list(?User $user): bool
    {
        return $user !== null
            && ($user->can(H5PPermissionsEnum::H5P_LIST) || $user->can(H5PPermissionsEnum::H5P_AUTHOR_LIST));
    }

    public function deleteUnused(?User $user): bool
    {
        return $user !== null && $user->can(H5PPermissionsEnum::H5P_DELETE);
    }
}
