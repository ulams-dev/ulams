<?php

namespace Ulams\HeadlessH5P\Policies;

use Ulams\Core\Models\User;
use Ulams\HeadlessH5P\Enums\H5PPermissionsEnum;
use Ulams\HeadlessH5P\Models\H5PContent;
use Illuminate\Auth\Access\HandlesAuthorization;

class H5PContentPolicy
{
    use HandlesAuthorization;

    public function list(?User $user): bool
    {
        return $user && ($user->can(H5PPermissionsEnum::H5P_LIST) || $user->can(H5PPermissionsEnum::H5P_AUTHOR_LIST));
    }

    public function read(?User $user): bool
    {
        return $user && $user->can(H5PPermissionsEnum::H5P_READ);
    }

    public function create(?User $user): bool
    {
        return $user && $user->can(H5PPermissionsEnum::H5P_CREATE);
    }

    public function delete(?User $user, H5PContent $h5PContent): bool
    {
        return $user &&
            ($user->can(H5PPermissionsEnum::H5P_DELETE) ||
            ($user->can(H5PPermissionsEnum::H5P_AUTHOR_DELETE) && $h5PContent->user_id == $user->getKey()));
    }

    public function update(?User $user, H5PContent $h5PContent): bool
    {
        if ($user && $user->can(H5PPermissionsEnum::H5P_AUTHOR_UPDATE) && !$user->can(H5PPermissionsEnum::H5P_UPDATE)) {
            return $h5PContent->user_id == $user->getKey();
        }

        return $user && $user->can(H5PPermissionsEnum::H5P_UPDATE);
    }
}
