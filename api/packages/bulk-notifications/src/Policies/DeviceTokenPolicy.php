<?php

namespace EscolaLms\BulkNotifications\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\BulkNotifications\Enums\BulkNotificationPermissionEnum;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeviceTokenPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->can(BulkNotificationPermissionEnum::CREATE_DEVICE_TOKEN);
    }
}
