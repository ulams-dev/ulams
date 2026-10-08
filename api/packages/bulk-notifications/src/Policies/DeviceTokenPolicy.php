<?php

namespace Ulams\BulkNotifications\Policies;

use Ulams\Auth\Models\User;
use Ulams\BulkNotifications\Enums\BulkNotificationPermissionEnum;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeviceTokenPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->can(BulkNotificationPermissionEnum::CREATE_DEVICE_TOKEN);
    }
}
