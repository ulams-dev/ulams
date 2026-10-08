<?php

namespace Ulams\Bookmarks\Policies;

use Ulams\Auth\Models\User;
use Ulams\Bookmarks\Enums\BookmarkPermissionEnum;
use Ulams\Bookmarks\Models\Bookmark;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookmarkPolicy
{
    use HandlesAuthorization;

    public function createOwn(User $user): bool
    {
        return $user->can(BookmarkPermissionEnum::CREATE_BOOKMARK_OWN);
    }

    public function updateOwn(User $user, Bookmark $bookmark): bool
    {
        return $user->can(BookmarkPermissionEnum::UPDATE_BOOKMARK_OWN) && $this->isOwner($bookmark);
    }

    public function deleteOwn(User $user, Bookmark $bookmark): bool
    {
        return $user->can(BookmarkPermissionEnum::DELETE_BOOKMARK_OWN) && $this->isOwner($bookmark);
    }

    public function listOwn(User $user): bool
    {
        return $user->can(BookmarkPermissionEnum::LIST_BOOKMARK_OWN);
    }

    public function create(User $user): bool
    {
        return $user->can(BookmarkPermissionEnum::CREATE_BOOKMARK);
    }

    public function update(User $user, Bookmark $bookmark): bool
    {
        return $user->can(BookmarkPermissionEnum::UPDATE_BOOKMARK);
    }

    public function delete(User $user, Bookmark $bookmark): bool
    {
        return $user->can(BookmarkPermissionEnum::DELETE_BOOKMARK);
    }

    public function list(User $user): bool
    {
        return $user->can(BookmarkPermissionEnum::LIST_BOOKMARK);
    }

    private function isOwner(Bookmark $bookmark): bool
    {
        return $bookmark->user_id === auth()->id();
    }
}
