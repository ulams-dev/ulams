<?php

namespace Ulams\StationaryEvents\Policies;

use Ulams\Auth\Models\User;
use Ulams\StationaryEvents\Enum\StationaryEventPermissionsEnum;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Illuminate\Auth\Access\HandlesAuthorization;

class StationaryEventPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(StationaryEventPermissionsEnum::STATIONARY_EVENT_LIST);
    }

    public function read(User $user, StationaryEvent $stationaryEvent): bool
    {
        return $user->can(StationaryEventPermissionsEnum::STATIONARY_EVENT_READ);
    }

    public function create(User $user): bool
    {
        return $user->can(StationaryEventPermissionsEnum::STATIONARY_EVENT_CREATE);
    }

    public function delete(User $user, StationaryEvent $stationaryEvent): bool
    {
        return $user->can(StationaryEventPermissionsEnum::STATIONARY_EVENT_DELETE);
    }

    public function update(User $user, StationaryEvent $stationaryEvent): bool
    {
        return $user->can(StationaryEventPermissionsEnum::STATIONARY_EVENT_UPDATE);
    }
}
