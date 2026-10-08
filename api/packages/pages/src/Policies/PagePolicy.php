<?php

namespace Ulams\Pages\Policies;

use Ulams\Core\Models\User;
use Ulams\Pages\Enums\PagesPermissionsEnum;
use Ulams\Pages\Models\Page;
use Illuminate\Auth\Access\HandlesAuthorization;

class PagePolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(PagesPermissionsEnum::PAGE_LIST);
    }

    public function read(User $user, Page $page): bool
    {
        return $user->can(PagesPermissionsEnum::PAGE_READ);
    }

    public function readFront(?User $user, Page $page): bool
    {
        return $page->active ?? false;
    }

    public function create(User $user): bool
    {
        return $user->can(PagesPermissionsEnum::PAGE_CREATE);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can(PagesPermissionsEnum::PAGE_DELETE);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can(PagesPermissionsEnum::PAGE_UPDATE);
    }
}
