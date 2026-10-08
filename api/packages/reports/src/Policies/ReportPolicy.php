<?php

namespace Ulams\Reports\Policies;

use Ulams\Reports\Enums\ReportsPermissionsEnum;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class ReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(ReportsPermissionsEnum::DISPLAY_REPORTS);
    }
}
