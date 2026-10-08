<?php

namespace EscolaLms\Cmi5\Policies;

use EscolaLms\Cmi5\Enums\Cmi5PermissionEnum;
use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class Cmi5Policy
{
    use HandlesAuthorization;

    public function upload(User $user): bool
    {
        return $user->can(Cmi5PermissionEnum::CMI5_UPLOAD);
    }

    public function list(User $user): bool
    {
        return $user->can(Cmi5PermissionEnum::CMI5_LIST);
    }

    public function read(User $user): bool
    {
        return $user->can(Cmi5PermissionEnum::CMI5_READ);
    }

    public function delete(User $user, Cmi5 $cmi5): bool
    {
        return $user->can(Cmi5PermissionEnum::CMI5_READ);
    }
}
