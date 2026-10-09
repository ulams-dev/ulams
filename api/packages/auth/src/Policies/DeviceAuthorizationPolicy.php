<?php

namespace Ulams\Auth\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;
use Ulams\Auth\Models\DeviceAuthorization;

/**
 * Any signed-in user may approve or deny a pending device request for their own account: the
 * resulting token belongs to the approver and is limited by the approver's own permissions.
 * Whether a request can still be answered is the request's own state, not a role.
 */
class DeviceAuthorizationPolicy
{
    use HandlesAuthorization;

    public function view(User $user, DeviceAuthorization $authorization): bool
    {
        return $authorization->isPending();
    }

    public function approve(User $user, DeviceAuthorization $authorization): bool
    {
        return $authorization->isPending();
    }

    public function deny(User $user, DeviceAuthorization $authorization): bool
    {
        return $authorization->isPending();
    }
}
