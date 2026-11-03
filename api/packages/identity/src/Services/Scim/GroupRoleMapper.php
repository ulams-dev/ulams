<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\User;
use Ulams\Core\Enums\UserRole;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\IdentityAuditLog;
use Ulams\Identity\Support\Users;

/**
 * Roles from SCIM group membership (plan 4.4, adapted to SCIM). The provider maps the display name of a group it manages
 * to `student` or `tutor`; a member of such a group has the role for as long as they are a member. Only roles this
 * mapping added are ever taken away again (a role the person had before stays), `admin` can never be mapped or touched,
 * and every grant and revocation is an audit row.
 */
class GroupRoleMapper
{
    public const MAPPABLE = [UserRole::STUDENT, UserRole::TUTOR];

    public function __construct(private readonly IdentityAuditLog $audit)
    {
    }

    /** Brings the user's roles in line with the groups they are in at this provider. */
    public function sync(IdentityProvider $provider, User $user): void
    {
        $ext = ExternalId::query()->where(['provider_id' => $provider->getKey(), 'resource_type' => ExternalId::USER, 'local_id' => $user->getKey()])->first();
        if ($ext === null) {
            return;
        }
        $map = array_filter((array) $provider->scim_group_roles, fn ($role) => in_array($role, self::MAPPABLE, true));
        $meta = (array) $ext->meta;
        $tracked = array_values(array_filter((array) ($meta['mapped_roles'] ?? []), fn ($r) => in_array($r, self::MAPPABLE, true)));
        if ($map === [] && $tracked === []) {
            return;
        }
        if ($user->hasRole(UserRole::ADMIN)) {
            return;
        }

        $wanted = [];
        if ($map !== []) {
            $names = DB::table('group_user as gu')
                ->join('groups', 'groups.id', '=', 'gu.group_id')
                ->join('identity_external_ids as x', function ($join) use ($provider) {
                    $join->on('x.local_id', '=', 'groups.id')->where('x.provider_id', '=', $provider->getKey())->where('x.resource_type', '=', ExternalId::GROUP)->whereNull('x.deleted_at');
                })
                ->where('gu.user_id', $user->getKey())->whereNull('groups.deleted_at')->pluck('groups.name');
            foreach ($names as $name) {
                $role = $map[mb_strtolower((string) $name)] ?? null;
                if ($role !== null) {
                    $wanted[$role] = true;
                }
            }
        }
        $wanted = array_keys($wanted);
        $current = $user->getRoleNames()->all();

        foreach (array_diff($wanted, $current) as $role) {
            $user->assignRole($role);
            $tracked[] = $role;
            $this->record('role.granted_by_mapping', $provider, $user, $role);
        }
        foreach (array_diff($tracked, $wanted) as $role) {
            if (in_array($role, $current, true)) {
                $user->removeRole($role);
                $this->record('role.revoked_by_mapping', $provider, $user, $role);
            }
        }
        $tracked = array_values(array_intersect(array_unique($tracked), $wanted));
        if ($tracked !== (array) ($meta['mapped_roles'] ?? [])) {
            $meta['mapped_roles'] = $tracked;
            $ext->forceFill(['meta' => $meta])->save();
        }
    }

    /** After the mapping of a provider changed: every user it provisioned. */
    public function resyncProvider(IdentityProvider $provider): void
    {
        ExternalId::query()->where(['provider_id' => $provider->getKey(), 'resource_type' => ExternalId::USER])->whereNull('deleted_at')
            ->chunkById(200, function ($rows) use ($provider) {
                foreach ($rows as $row) {
                    $user = Users::find($row->local_id);
                    if ($user !== null) {
                        $this->sync($provider, $user);
                    }
                }
            });
    }

    private function record(string $action, IdentityProvider $provider, User $user, string $role): void
    {
        $this->audit->record($action, [
            'actor' => 'scim', 'actor_id' => $provider->scim_user_id, 'provider_id' => $provider->getKey(),
            'subject_type' => 'user', 'subject_id' => $user->getKey(), 'data' => ['role' => $role],
        ]);
    }
}
