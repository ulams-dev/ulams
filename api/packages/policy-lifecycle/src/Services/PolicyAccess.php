<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Ulams\PolicyLifecycle\Enums\PolicyPermissionsEnum;
use Ulams\PolicyLifecycle\Models\Policy;

/**
 * Who may act on a policy: `policy_manage` on all of them, `policy_owner` on the ones the user owns.
 */
final class PolicyAccess
{
    public function manageAll(?Authenticatable $user): bool
    {
        return $user !== null && (bool) $user->can(PolicyPermissionsEnum::MANAGE, 'api');
    }

    public function mayUse(?Authenticatable $user): bool
    {
        return $this->manageAll($user) || ($user !== null && (bool) $user->can(PolicyPermissionsEnum::OWNER, 'api'));
    }

    public function allows(?Authenticatable $user, Policy $policy): bool
    {
        if ($this->manageAll($user)) {
            return true;
        }

        return $user !== null && $user->can(PolicyPermissionsEnum::OWNER, 'api') && (int) $policy->owner_user_id === (int) $user->getAuthIdentifier();
    }

    /** @param Builder<Policy> $query */
    public function scope(Builder $query, ?Authenticatable $user): Builder
    {
        return $this->manageAll($user) ? $query : $query->where('owner_user_id', $user?->getAuthIdentifier() ?? 0);
    }
}
