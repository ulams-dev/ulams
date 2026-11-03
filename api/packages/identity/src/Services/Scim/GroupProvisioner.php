<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Dtos\UserGroupDto;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\IdentityAuditLog;

/**
 * Writes SCIM group changes through `UserGroupService` (CLAUDE.md non-negotiables). A group a client creates is flagged
 * as managed by it (an `identity_external_ids` row): only that provider sees it, and only the members that provider
 * provisioned are shown, added or removed (members an administrator added by hand are never touched or revealed). A client
 * never sees, renames, fills or deletes a group it did not create, and a display name already used by any other group is
 * a 409. Members are users of the same provider; groups cannot be nested.
 */
class GroupProvisioner
{
    public function __construct(
        private readonly UserGroupServiceContract $groups,
        private readonly GroupRoleMapper $roles,
        private readonly IdentityAuditLog $audit,
    ) {
    }

    /** @param array{displayName:string,externalId:?string,members:?list<string>} $in
     * @return array{0:Group,1:ExternalId}
     */
    public function create(IdentityProvider $provider, array $in): array
    {
        try {
            return DB::transaction(function () use ($provider, $in) {
                $this->assertFree($provider, $in['displayName'], $in['externalId'], null, null);
                $members = $this->resolveMembers($provider, $in['members'] ?? []);
                $group = $this->groups->create(new UserGroupDto($in['displayName']));
                $ext = ExternalId::query()->create([
                    'provider_id' => $provider->getKey(), 'resource_type' => ExternalId::GROUP, 'local_id' => $group->getKey(),
                    'external_id' => $in['externalId'], 'source' => 'scim',
                ]);
                $added = $this->groups->addMembers($group, $members);
                $this->resync($provider, $members);
                $this->record('scim.group_created', $provider, $group, ['members' => $added]);

                return [$group->refresh(), $ext->refresh()];
            });
        } catch (UniqueConstraintViolationException) {
            throw ScimError::uniqueness('The displayName or externalId is already in use.');
        }
    }

    /**
     * PUT and PATCH. `members` null (not sent) leaves the membership alone, so a replace that only renames cannot empty a group.
     *
     * @param array{displayName:string,externalId:?string,members:?list<string>} $in
     */
    public function update(IdentityProvider $provider, Group $group, ExternalId $ext, array $in): Group
    {
        try {
            DB::transaction(function () use ($provider, $group, $ext, $in) {
                $this->assertFree($provider, $in['displayName'], $in['externalId'], $group, $ext);
                $changed = [];
                $affected = [];
                if ($group->name !== $in['displayName']) {
                    $this->groups->update($group, new UserGroupDto($in['displayName'], (bool) $group->registerable, $group->parent_id));
                    $changed[] = 'displayName';
                    // a different name can mean a different role mapping for everybody in the group
                    $affected = $this->visibleMembers($provider, $group);
                }
                if ($ext->external_id !== $in['externalId']) {
                    $ext->forceFill(['external_id' => $in['externalId']])->save();
                    $changed[] = 'externalId';
                }
                if ($in['members'] !== null) {
                    $wanted = $this->resolveMembers($provider, $in['members']);
                    $current = $this->visibleMembers($provider, $group);
                    $wantedIds = array_map(fn (User $u) => $u->getKey(), $wanted);
                    $currentIds = array_map(fn (User $u) => $u->getKey(), $current);
                    $added = $this->groups->addMembers($group, array_filter($wanted, fn (User $u) => !in_array($u->getKey(), $currentIds, true)));
                    $removed = $this->groups->removeMembers($group, array_filter($current, fn (User $u) => !in_array($u->getKey(), $wantedIds, true)));
                    if ($added + $removed > 0) {
                        $this->record('scim.group_members_changed', $provider, $group, ['added' => $added, 'removed' => $removed]);
                        $affected = [...$affected, ...$wanted, ...$current];
                    }
                }
                if ($changed !== [] || $affected !== []) {
                    $ext->touch();
                }
                $this->resync($provider, $affected);
                if ($changed !== []) {
                    $this->record('scim.group_updated', $provider, $group, ['changed' => $changed]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ScimError::uniqueness('The displayName or externalId is already in use.');
        }

        return $group->refresh();
    }

    /** DELETE /Groups/{id}: the group goes (soft delete, as in the admin API) and so does the access it gave; the people stay. */
    public function delete(IdentityProvider $provider, Group $group, ExternalId $ext): void
    {
        DB::transaction(function () use ($provider, $group, $ext) {
            $members = $this->visibleMembers($provider, $group);
            $this->groups->delete($group);
            $ext->delete();
            $this->resync($provider, $members);
            $this->record('scim.group_deleted', $provider, $group, ['members' => count($members)]);
        });
    }

    /** A user deprovisioned by DELETE leaves the groups this provider manages. */
    public function leaveAll(IdentityProvider $provider, User $user): void
    {
        $groups = ScimDirectory::groups($provider)->whereIn('groups.id', DB::table('group_user')->where('user_id', $user->getKey())->pluck('group_id'))->get();
        foreach ($groups as $group) {
            $this->groups->removeMembers($group, [$user]);
        }
        if ($groups->isNotEmpty()) {
            $this->roles->sync($provider, $user);
        }
    }

    /** @return list<User> the members of the group that this provider provisioned */
    public function visibleMembers(IdentityProvider $provider, Group $group): array
    {
        return ScimDirectory::users($provider)->whereIn('users.id', DB::table('group_user')->where('group_id', $group->getKey())->select('user_id'))->get()->all();
    }

    /**
     * @param list<string> $ids
     * @return list<User>
     */
    private function resolveMembers(IdentityProvider $provider, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $users = ScimDirectory::users($provider)->whereIn('users.id', array_map('intval', $ids))->get()->keyBy(fn (User $u) => (string) $u->getKey());
        $out = [];
        foreach ($ids as $id) {
            if (!isset($users[$id])) {
                throw ScimError::invalidValue("Member {$id} is not a user of this identity provider.");
            }
            $out[] = $users[$id];
        }

        return $out;
    }

    private function assertFree(IdentityProvider $provider, string $name, ?string $externalId, ?Group $self, ?ExternalId $selfExt): void
    {
        $taken = Group::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($self !== null, fn ($q) => $q->where('id', '!=', $self->getKey()))->exists();
        if ($taken) {
            throw ScimError::uniqueness('A group with this displayName already exists.');
        }
        if ($externalId !== null && ExternalId::query()->where(['provider_id' => $provider->getKey(), 'resource_type' => ExternalId::GROUP, 'external_id' => $externalId])
            ->when($selfExt !== null, fn ($q) => $q->where('id', '!=', $selfExt->getKey()))->exists()) {
            throw ScimError::uniqueness('The externalId is already in use.');
        }
    }

    /** @param list<User> $users */
    private function resync(IdentityProvider $provider, array $users): void
    {
        $seen = [];
        foreach ($users as $user) {
            if (!isset($seen[$user->getKey()])) {
                $seen[$user->getKey()] = true;
                $this->roles->sync($provider, $user->refresh());
            }
        }
    }

    /** @param array<string,mixed> $data */
    private function record(string $action, IdentityProvider $provider, Group $group, array $data = []): void
    {
        $this->audit->record($action, [
            'actor' => 'scim', 'actor_id' => $provider->scim_user_id, 'provider_id' => $provider->getKey(),
            'subject_type' => 'group', 'subject_id' => $group->getKey(), 'data' => $data,
        ]);
    }
}
