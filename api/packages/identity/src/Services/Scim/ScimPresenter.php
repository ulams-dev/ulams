<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\Group;
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

    /** @return array<string,mixed> */
    public function group(IdentityProvider $provider, Group $group, ExternalId $ext, string $base): array
    {
        $members = ScimDirectory::users($provider)
            ->whereIn('users.id', DB::table('group_user')->where('group_id', $group->getKey())->select('user_id'))
            ->orderBy('users.id')->get()
            ->map(fn (User $u) => [(int) $u->getKey(), trim($u->first_name . ' ' . $u->last_name)])->all();

        return GroupMapper::toScim($group, $ext, $base, $members);
    }

    /** @return list<array{0:int,1:string}> the groups of this provider the user is in */
    protected function groupsOf(IdentityProvider $provider, User $user): array
    {
        return ScimDirectory::groups($provider)
            ->whereIn('groups.id', DB::table('group_user')->where('user_id', $user->getKey())->select('group_id'))
            ->orderBy('groups.id')->get()
            ->map(fn (Group $g) => [(int) $g->getKey(), (string) $g->name])->all();
    }
}
