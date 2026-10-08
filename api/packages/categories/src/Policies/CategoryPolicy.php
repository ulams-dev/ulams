<?php


namespace Ulams\Categories\Policies;

use Ulams\Categories\Enums\CategoriesPermissionsEnum;
use Ulams\Categories\Models\Category;
use Ulams\Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(CategoriesPermissionsEnum::CATEGORY_LIST);
    }

    public function read(User $user): bool
    {
        return $user->can(CategoriesPermissionsEnum::CATEGORY_READ);
    }

    /**
     * @param User $user
     * @param Category $category
     * @return bool
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can(CategoriesPermissionsEnum::CATEGORY_UPDATE);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can(CategoriesPermissionsEnum::CATEGORY_CREATE);
    }

    /**
     * @param User $user
     * @param Category $category
     * @return bool
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can(CategoriesPermissionsEnum::CATEGORY_DELETE);
    }
}
