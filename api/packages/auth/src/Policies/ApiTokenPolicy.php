<?php

namespace Ulams\Auth\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;
use Ulams\Auth\Enums\AuthPermissionsEnum;
use Ulams\Auth\Models\ApiTokenMeta;

/**
 * Everyone manages their own scoped tokens; `token_manage` (admins) lists and revokes everybody's
 * and reads the agent audit log. Whether a token may be *used* is decided by its scopes, not here.
 */
class ApiTokenPolicy
{
    use HandlesAuthorization;

    /** List and create own tokens. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** List every user's tokens, read the audit log. */
    public function manage(User $user): bool
    {
        return $user->can(AuthPermissionsEnum::TOKEN_MANAGE);
    }

    public function delete(User $user, ApiTokenMeta $meta): bool
    {
        return $this->owns($user, $meta) || $this->manage($user);
    }

    public function viewAudit(User $user, ApiTokenMeta $meta): bool
    {
        return $this->owns($user, $meta) || $this->manage($user);
    }

    private function owns(User $user, ApiTokenMeta $meta): bool
    {
        return (string) $meta->token?->user_id === (string) $user->getKey();
    }
}
