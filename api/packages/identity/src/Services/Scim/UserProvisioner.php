<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\IdentityAuditLog;
use Ulams\Identity\Support\Users;
use Ulams\ModelFields\Facades\ModelFields;

/**
 * Writes SCIM user changes through the domain services (UserService, model fields, ManagerService), never to tables of
 * `auth` directly (CLAUDE.md non-negotiables).
 *
 * Rules (plan 4.7, ADR 0121, ADR 0198):
 * - A client sees and changes only users it provisioned. A userName or e-mail address that belongs to an account the
 *   provider did not provision is a 409 `uniqueness`, except when the provider is trusted for the address's domain
 *   (the same test as linking at sign-in: `trust_email`, domain of the organisation, verified address, active,
 *   not an administrator or tutor): then the account is adopted.
 * - Administrators and superadministrators cannot be changed, deactivated or deleted through SCIM.
 * - `active=false` deactivates (tokens and sessions are revoked by the `AccountBlocked` listener of `auth`);
 *   `DELETE` deactivates, unlinks and keeps the learning record, or with `delete_behaviour = anonymise` masks the
 *   account through the existing `MaskUserData` listener. Nothing is hard-deleted.
 */
class UserProvisioner
{
    public function __construct(private readonly UserServiceContract $users, private readonly IdentityAuditLog $log)
    {
    }

    /**
     * @param array<string,mixed> $in the output of UserMapper::parse
     * @return array{0:User,1:ExternalId,2:string} user, external id row, `created` | `adopted` | `revived`
     */
    public function create(IdentityProvider $provider, array $in): array
    {
        $key = Str::lower($in['userName']);

        try {
            return DB::transaction(function () use ($provider, $in, $key) {
                $byName = ExternalId::query()->where('resource_type', ExternalId::USER)->where('user_name_key', $key)->first();
                $byExternal = $in['externalId'] === null ? null : ExternalId::query()->where(['provider_id' => $provider->getKey(), 'resource_type' => ExternalId::USER, 'external_id' => $in['externalId']])->first();
                foreach ([$byName, $byExternal] as $row) {
                    if ($row !== null && $row->deleted_at === null) {
                        throw ScimError::uniqueness($row === $byName ? 'The userName is already in use.' : 'The externalId is already in use.');
                    }
                }
                $tomb = $byName ?? $byExternal;
                if ($byName !== null && $byExternal !== null && $byName->getKey() !== $byExternal->getKey()) {
                    throw ScimError::uniqueness('The userName and the externalId belong to different earlier resources.');
                }
                if ($tomb !== null) {
                    if ($tomb->provider_id !== $provider->getKey()) {
                        throw ScimError::uniqueness('The userName is already in use.');
                    }
                    $user = Users::withTrashed()->find($tomb->local_id);
                    if ($user === null || $user->trashed()) {
                        throw ScimError::uniqueness('The earlier account for this userName was removed.');
                    }
                    $this->assertManageable($user);
                    $tomb->forceFill(['deleted_at' => null])->save();
                    $this->write($provider, $user, $tomb, $in, $in['active'] ?? true);
                    $this->audit('scim.user_created', $provider, $user, ['revived' => true, 'active' => (bool) $user->refresh()->is_active]);

                    return [$user->refresh(), $tomb->refresh(), 'revived'];
                }

                $existing = Users::withTrashed()->whereRaw('LOWER(email) = ?', [$in['email']])->first();
                if ($existing !== null) {
                    if (!$this->mayAdopt($provider, $existing, $in['email'])) {
                        throw ScimError::uniqueness('The e-mail address belongs to an account that this identity provider did not provision.');
                    }
                    $ext = $this->newExternal($provider, $existing, $in);
                    $this->write($provider, $existing, $ext, $in, $in['active'] ?? true);
                    $this->audit('scim.user_created', $provider, $existing, ['adopted' => true, 'active' => (bool) $existing->refresh()->is_active]);

                    return [$existing->refresh(), $ext->refresh(), 'adopted'];
                }

                $user = $this->users->create(new UserSaveDto($in['given'], $in['family'], $in['active'] ?? true, [$this->role($provider)], $in['email'], null, null, true));
                $ext = $this->newExternal($provider, $user, $in);
                $this->write($provider, $user, $ext, $in, null);
                $this->audit('scim.user_created', $provider, $user, ['active' => (bool) $user->refresh()->is_active]);

                return [$user->refresh(), $ext->refresh(), 'created'];
            });
        } catch (UniqueConstraintViolationException) {
            throw ScimError::uniqueness('The userName, externalId or e-mail address is already in use.');
        }
    }

    /**
     * PUT and PATCH: replace what the client sent. An omitted `active` keeps the current state, so a replace that
     * leaves it out can never switch an account back on.
     *
     * @param array<string,mixed> $in
     */
    public function update(IdentityProvider $provider, User $user, ExternalId $ext, array $in): User
    {
        $this->assertManageable($user);
        try {
            DB::transaction(function () use ($provider, $user, $ext, $in) {
                $key = Str::lower($in['userName']);
                if ($key !== $ext->user_name_key && ExternalId::query()->where('resource_type', ExternalId::USER)->where('user_name_key', $key)->where('id', '!=', $ext->getKey())->exists()) {
                    throw ScimError::uniqueness('The userName is already in use.');
                }
                if ($in['externalId'] !== null && $in['externalId'] !== $ext->external_id && ExternalId::query()->where(['provider_id' => $provider->getKey(), 'resource_type' => ExternalId::USER, 'external_id' => $in['externalId']])->where('id', '!=', $ext->getKey())->exists()) {
                    throw ScimError::uniqueness('The externalId is already in use.');
                }
                if (Str::lower((string) $user->email) !== $in['email'] && Users::withTrashed()->whereRaw('LOWER(email) = ?', [$in['email']])->where('id', '!=', $user->getKey())->exists()) {
                    throw ScimError::uniqueness('The e-mail address is already in use.');
                }
                $before = (bool) $user->is_active;
                $changed = $this->write($provider, $user, $ext, $in, $in['active'] ?? $before);
                $after = (bool) $user->refresh()->is_active;
                if ($before && !$after) {
                    $this->audit('scim.user_deactivated', $provider, $user, ['via' => 'active']);
                } elseif (!$before && $after) {
                    $this->audit('scim.user_reactivated', $provider, $user);
                }
                // a change of `active` has its own audit entry above
                $others = array_values(array_diff($changed, ['active']));
                if ($others !== []) {
                    $this->audit('scim.user_updated', $provider, $user, ['changed' => $others]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ScimError::uniqueness('The userName, externalId or e-mail address is already in use.');
        }

        return $user->refresh();
    }

    /** DELETE /Users/{id}: deactivate and unlink (default) or anonymise; the resource is gone for the client either way. */
    public function delete(IdentityProvider $provider, User $user, ExternalId $ext): void
    {
        $this->assertManageable($user);
        $behaviour = config('ulams_identity.scim.delete_behaviour') === 'anonymise' ? 'anonymise' : 'deactivate';
        DB::transaction(function () use ($provider, $user, $ext, $behaviour) {
            if ($behaviour === 'anonymise') {
                // soft delete: the existing listeners revoke tokens and mask the personal data, learning records stay
                $user->delete();
                $ext->forceFill(['deleted_at' => now(), 'external_id' => null, 'user_name' => null, 'user_name_key' => null, 'meta' => null])->save();
            } else {
                if ($user->is_active) {
                    $this->users->update($user, new UserSaveDto((string) $user->first_name, (string) $user->last_name, false, $user->getRoleNames()->all(), null, null, null, false));
                }
                $ext->forceFill(['deleted_at' => now()])->save();
            }
            IdentityLink::query()->where(['provider_id' => $provider->getKey(), 'user_id' => $user->getKey()])->delete();
            $this->audit('scim.user_deleted', $provider, $user, ['behaviour' => $behaviour]);
        });
    }

    public function assertManageable(User $user): void
    {
        $superadmins = array_map('strtolower', array_filter((array) config(UlamsAuthServiceProvider::CONFIG_KEY . '.superadmins', [])));
        if ($user->hasRole(UserRole::ADMIN) || in_array(Str::lower((string) $user->email), $superadmins, true)) {
            throw ScimError::forbidden('Administrators cannot be changed through SCIM.');
        }
    }

    private function mayAdopt(IdentityProvider $provider, User $existing, string $email): bool
    {
        if ($existing->trashed() || !$existing->is_active || !$existing->hasVerifiedEmail()) {
            return false;
        }
        if (!$provider->trust_email || !$provider->ownsDomainOf($email)) {
            return false;
        }
        $superadmins = array_map('strtolower', array_filter((array) config(UlamsAuthServiceProvider::CONFIG_KEY . '.superadmins', [])));

        return !in_array($email, $superadmins, true) && !$existing->hasAnyRole([UserRole::ADMIN, UserRole::TUTOR]);
    }

    /** @param array<string,mixed> $in */
    private function newExternal(IdentityProvider $provider, User $user, array $in): ExternalId
    {
        return ExternalId::query()->create([
            'provider_id' => $provider->getKey(),
            'resource_type' => ExternalId::USER,
            'local_id' => $user->getKey(),
            'external_id' => $in['externalId'],
            'user_name' => $in['userName'],
            'user_name_key' => Str::lower($in['userName']),
            'source' => 'scim',
        ]);
    }

    /**
     * Applies the parsed document to the user, its external id row, its model fields and its manager link.
     *
     * @param array<string,mixed> $in
     * @return list<string> names of the attributes that changed
     */
    private function write(IdentityProvider $provider, User $user, ExternalId $ext, array $in, ?bool $active): array
    {
        $changed = [];
        $attributes = ['first_name' => $in['given'], 'last_name' => $in['family'], 'email' => $in['email']];
        foreach ($attributes as $column => $value) {
            if ((string) $user->{$column} !== $value) {
                $changed[] = $column === 'email' ? 'emails' : ($column === 'first_name' ? 'name.givenName' : 'name.familyName');
            }
        }
        $wasActive = (bool) $user->is_active;
        $target = $active ?? $wasActive;
        if ($target !== $wasActive) {
            $changed[] = 'active';
        }
        if (array_diff($changed, ['active']) !== [] || $target !== $wasActive) {
            $this->users->update($user, new UserSaveDto($in['given'], $in['family'], $target, $user->getRoleNames()->all(), $in['email'], null, null, false));
            $user->refresh();
        }

        // the displayName the server derives from the name is not a value the client chose
        $display = $in['displayName'] === trim($in['given'] . ' ' . $in['family']) ? null : $in['displayName'];
        $meta = [
            'displayName' => $display,
            'title' => $in['title'],
            'enterprise' => array_filter($in['enterprise'], fn ($v) => $v !== null),
            'manager' => null,
        ];
        if ($in['manager'] !== null) {
            if ($in['manager'] === (string) $user->getKey()) {
                throw ScimError::invalidValue('A user cannot be their own manager.');
            }
            $manager = ScimDirectory::users($provider)->where('users.id', (int) $in['manager'])->first();
            if ($manager === null) {
                throw ScimError::invalidValue('manager.value does not refer to a user of this identity provider.');
            }
            $meta['manager'] = (string) $manager->getKey();
        }
        $before = (array) $ext->meta;
        $norm = static fn ($v) => $v === [] || $v === '' ? null : $v;
        foreach (['displayName', 'title', 'enterprise', 'manager'] as $name) {
            if ($norm($before[$name] ?? null) !== $norm($meta[$name])) {
                $changed[] = $name;
            }
        }
        $newKey = Str::lower($in['userName']);
        if ($ext->user_name_key !== null && $ext->user_name_key !== $newKey) {
            $changed[] = 'userName';
        }
        if ($ext->external_id !== $in['externalId']) {
            $changed[] = 'externalId';
        }
        $ext->forceFill([
            'user_name' => $in['userName'], 'user_name_key' => $newKey, 'external_id' => $in['externalId'], 'meta' => $meta,
        ])->save();

        $this->mirrorFields($user, $in);
        $this->linkManager($user, $meta['manager']);

        return array_values(array_unique($changed));
    }

    /** Writes enterprise attributes and the title into the academy's own model fields when it defines them (never creates one). */
    private function mirrorFields(User $user, array $in): void
    {
        $defined = ModelFields::getFieldsMetadata(Users::model())->pluck('name')->all();
        $values = $in['enterprise'] + ['title' => $in['title']];
        $dirty = false;
        foreach (UserMapper::FIELD_MAP as $attribute => $field) {
            if (!in_array($field, $defined, true)) {
                continue;
            }
            $value = (string) ($values[$attribute] ?? '');
            if ((string) $user->getAttribute($field) !== $value) {
                $user->setAttribute($field, $value);
                $dirty = true;
            }
        }
        if ($dirty) {
            $user->save();
        }
    }

    private function linkManager(User $user, ?string $managerId): void
    {
        $service = \Ulams\Compliance\Services\ManagerService::class;
        if (!class_exists($service) || !Schema::hasTable('compliance_manager_links')) {
            return;
        }
        app($service)->link((int) $user->getKey(), $managerId === null ? null : (int) $managerId);
    }

    private function role(IdentityProvider $provider): string
    {
        return $provider->default_role === UserRole::TUTOR ? UserRole::TUTOR : UserRole::STUDENT;
    }

    /** @param array<string,mixed> $data */
    private function audit(string $action, IdentityProvider $provider, User $user, array $data = []): void
    {
        $this->log->record($action, [
            'actor' => 'scim',
            'actor_id' => $provider->scim_user_id,
            'provider_id' => $provider->getKey(),
            'subject_type' => 'user',
            'subject_id' => $user->getKey(),
            'data' => $data,
        ]);
    }
}
