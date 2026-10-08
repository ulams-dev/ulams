<?php

namespace EscolaLms\StationaryEvents\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\StationaryEvents\Enum\StationaryEventPermissionsEnum;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
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
