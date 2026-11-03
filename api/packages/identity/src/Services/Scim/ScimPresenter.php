<?php

namespace Ulams\Identity\Services\Scim;

use Ulams\Auth\Models\User;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityProvider;

/** Builds the SCIM representation of a user (with the groups and the manager the provider knows about). */
class ScimPresenter
{
    /** @return array<string,mixed> */
    public function user(IdentityProvider $provider, User $user, ExternalId $ext, string $base): array
    {
        $managerId = ((array) $ext->meta)['manager'] ?? null;
        $manager = $managerId === null ? null : ScimDirectory::users($provider)->where('users.id', (int) $managerId)->first();

        return UserMapper::toScim($user, $ext, $base, $this->groupsOf($provider, $user), $manager);
    }

    /** @return list<array{0:int,1:string}> */
    protected function groupsOf(IdentityProvider $provider, User $user): array
    {
        return [];
    }
}
