<?php

namespace Ulams\BulkNotifications\Policies;

use Ulams\Auth\Models\User;
use Ulams\BulkNotifications\Enums\BulkNotificationPermissionEnum;
use Illuminate\Auth\Access\HandlesAuthorization;

class BulkNotificationPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->can(BulkNotificationPermissionEnum::CREATE_BULK_NOTIFICATION);
    }

    public function list(User $user): bool
    {
        return $user->can(BulkNotificationPermissionEnum::LIST_BULK_NOTIFICATION);
    }
}
