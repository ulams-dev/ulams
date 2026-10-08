<?php

namespace Ulams\ModelFields\Policies;

use Ulams\Core\Models\User;
use Ulams\ModelFields\Enum\MetaFieldPermissionsEnum;
use Ulams\ModelFields\Models\Metadata;
use Illuminate\Auth\Access\HandlesAuthorization;

class MetadataPolicy
{
    use HandlesAuthorization;

    public function list(?User $user): bool
    {
        return true;
    }

    public function createOrUpdate(?User $user): bool
    {
        return !is_null($user) && $user->can(MetaFieldPermissionsEnum::METADATA_CREATE_UPDATE);
    }

    public function delete(?User $user, ?Metadata $template = null): bool
    {
        return !is_null($user) && $user->can(MetaFieldPermissionsEnum::METADATA_DELETE);
    }
}
