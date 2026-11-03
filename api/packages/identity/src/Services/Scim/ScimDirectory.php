<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Database\Eloquent\Builder;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Support\Users;

/**
 * What one provider's SCIM client can see: only the users and groups that provider provisioned (an external id row of
 * that provider, not deprovisioned). Everything else in the academy, administrators included, is invisible to it and
 * answers 404, so a SCIM token cannot read or change an account it did not create or adopt.
 */
final class ScimDirectory
{
    /** @return Builder<User> */
    public static function users(IdentityProvider $provider): Builder
    {
        return Users::model()::query()
            ->select('users.*')
            ->join('identity_external_ids as x', function ($join) use ($provider) {
                $join->on('x.local_id', '=', 'users.id')
                    ->where('x.provider_id', '=', $provider->getKey())
                    ->where('x.resource_type', '=', ExternalId::USER)
                    ->whereNull('x.deleted_at');
            });
    }

    /** @return Builder<Group> */
    public static function groups(IdentityProvider $provider): Builder
    {
        return Group::query()
            ->select('groups.*')
            ->join('identity_external_ids as x', function ($join) use ($provider) {
                $join->on('x.local_id', '=', 'groups.id')
                    ->where('x.provider_id', '=', $provider->getKey())
                    ->where('x.resource_type', '=', ExternalId::GROUP)
                    ->whereNull('x.deleted_at');
            });
    }

    /** @return array{0:User,1:ExternalId} */
    public static function user(IdentityProvider $provider, string $id): array
    {
        $user = preg_match('/^[0-9]{1,18}$/', $id) === 1 ? self::users($provider)->where('users.id', (int) $id)->first() : null;
        if ($user === null) {
            throw ScimError::notFound('User', mb_substr($id, 0, 64));
        }

        return [$user, self::external($provider, ExternalId::USER, (int) $user->getKey())];
    }

    /** @return array{0:Group,1:ExternalId} */
    public static function group(IdentityProvider $provider, string $id): array
    {
        $group = preg_match('/^[0-9]{1,18}$/', $id) === 1 ? self::groups($provider)->where('groups.id', (int) $id)->first() : null;
        if ($group === null) {
            throw ScimError::notFound('Group', mb_substr($id, 0, 64));
        }

        return [$group, self::external($provider, ExternalId::GROUP, (int) $group->getKey())];
    }

    public static function external(IdentityProvider $provider, string $type, int $localId): ExternalId
    {
        return ExternalId::query()->where(['provider_id' => $provider->getKey(), 'resource_type' => $type, 'local_id' => $localId])->firstOrFail();
    }
}
