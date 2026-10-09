<?php

namespace Ulams\Tags\Policies;

use Ulams\Core\Models\User;
use Ulams\Tags\Enums\TagsPermissionsEnum;
use Ulams\Tags\Models\Tag;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can(TagsPermissionsEnum::TAGS_CREATE);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function list(User $user): bool
    {
        return $user->can(TagsPermissionsEnum::TAGS_LIST);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        return $user->can(TagsPermissionsEnum::TAGS_DELETE);
    }
}
