<?php

namespace Ulams\Auth\Models\Traits;

use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\GroupUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read Collection $groups
 */
trait HasGroups
{

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->using(GroupUser::class);
    }

    public function belongsToGroup(Group $group): bool
    {
        return $this->relationLoaded('groups')
            ? $this->groups->contains('id', '=', $group->getKey())
            : $this->groups()->wherePivot('group_id', $group->getKey())->exists();
    }
}
