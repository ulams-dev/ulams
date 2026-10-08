<?php

namespace EscolaLms\Settings\Policies;

use EscolaLms\Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use EscolaLms\Settings\Enums\SettingsPermissionsEnum;

class SettingsPolicy
{

    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(SettingsPermissionsEnum::SETTINGS_LIST)
            || $user->can(SettingsPermissionsEnum::SETTINGS_LIST_READONLY);
    }

    public function view(User $user): bool
    {
        return $user->can(SettingsPermissionsEnum::SETTINGS_READ);
    }

    public function create(User $user): bool
    {
        return $user->can(SettingsPermissionsEnum::SETTINGS_CREATE);
    }

    public function update(User $user): bool
    {
        return $user->can(SettingsPermissionsEnum::SETTINGS_UPDATE);
    }

    public function delete(User $user): bool
    {
        return $user->can(SettingsPermissionsEnum::SETTINGS_DELETE);
    }
}
