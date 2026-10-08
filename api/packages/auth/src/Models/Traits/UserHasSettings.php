<?php

namespace Ulams\Auth\Models\Traits;

use Ulams\Auth\Models\UserSetting;
use Ulams\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserHasSettings
{
    use ExtendableModelTrait;

    public function settings(): HasMany
    {
        /** @var User $this */
        return $this->hasMany(UserSetting::class);
    }
}
