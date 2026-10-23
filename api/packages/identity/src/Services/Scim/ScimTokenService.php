<?php

namespace Ulams\Identity\Services\Scim;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Auth\Support\IssuedToken;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\IdentityAuditLog;
use Ulams\Identity\Support\Users;

/**
 * SCIM bearer tokens (ADR 0121): Passport personal access tokens of kind `scim` and scope `scim:write`, held by one
 * passwordless service user per provider. The user never signs in (no password, not verified, inactive), the token
 * is the only credential, it is shown once, lives at most 365 days and at most two are active at a time, so a
 * secret can be rotated without a gap: create the second, switch the client, revoke the first.
 */
class ScimTokenService
{
    public const MAX_ACTIVE = 2;
    public const SCOPE = 'scim:write';

    public function __construct(
        private readonly PersonalAccessTokenServiceContract $tokens,
        private readonly UserServiceContract $users,
        private readonly IdentityAuditLog $audit,
    ) {
    }

    /** @throws InvalidArgumentException when two tokens are already active */
    public function issue(IdentityProvider $provider, string $name, int $days): IssuedToken
    {
        $user = $this->serviceUser($provider);
        if ($this->active($user)->count() >= self::MAX_ACTIVE) {
            throw new InvalidArgumentException('At most ' . self::MAX_ACTIVE . ' SCIM tokens can be active for a provider; revoke one first (rotation: create the new token, switch the client, then revoke the old one).');
        }
        $issued = $this->tokens->issue($user, $name, [self::SCOPE], $days, ApiTokenMeta::KIND_SCIM, null, 'admin', null);
        // the secret is in the response only: the audit row names the token, never its value
        $this->audit->record('scim.token_created', [
            'provider_id' => $provider->getKey(),
            'subject_type' => 'token',
            'subject_id' => $issued->meta->getKey(),
            'data' => ['token_id' => $issued->meta->token_id, 'name' => mb_substr($name, 0, 100), 'expires_at' => $issued->token->expires_at?->toIso8601String()],
        ]);

        return $issued;
    }

    /** @return Collection<int,ApiTokenMeta> active and revoked tokens, newest first */
    public function list(IdentityProvider $provider): Collection
    {
        $user = $provider->scim_user_id === null ? null : Users::find($provider->scim_user_id);

        return $user === null ? collect() : $this->tokens->listFor($user, true);
    }

    public function find(IdentityProvider $provider, string $tokenId): ?ApiTokenMeta
    {
        $meta = $this->tokens->find($tokenId);
        if ($meta === null || $meta->kind !== ApiTokenMeta::KIND_SCIM || $provider->scim_user_id === null || (int) $meta->token?->user_id !== (int) $provider->scim_user_id) {
            return null;
        }

        return $meta;
    }

    public function revoke(IdentityProvider $provider, ApiTokenMeta $meta): void
    {
        $this->tokens->revoke($meta);
        $this->audit->record('scim.token_revoked', ['provider_id' => $provider->getKey(), 'subject_type' => 'token', 'subject_id' => $meta->getKey(), 'data' => ['token_id' => $meta->token_id]]);
    }

    /** Every token of the provider stops working (provider deleted). Returns how many were active. */
    public function revokeAll(IdentityProvider $provider): int
    {
        $user = $provider->scim_user_id === null ? null : Users::find($provider->scim_user_id);
        if ($user === null) {
            return 0;
        }
        $active = $this->active($user);
        foreach ($active as $meta) {
            $this->tokens->revoke($meta);
        }

        return $active->count();
    }

    /** @return Collection<int,ApiTokenMeta> */
    private function active(User $user): Collection
    {
        return $this->tokens->listFor($user, false);
    }

    private function serviceUser(IdentityProvider $provider): User
    {
        if ($provider->scim_user_id !== null && ($user = Users::find($provider->scim_user_id)) !== null) {
            return $user;
        }
        $email = 'scim+' . $provider->slug . '@identity.invalid';
        $user = Users::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null) {
            // no password, no verified address, inactive: it cannot sign in by any method, it only owns tokens
            $user = $this->users->create(new UserSaveDto('SCIM', mb_substr($provider->slug, 0, 100), false, [], $email, null, null, false));
        }
        $provider->forceFill(['scim_user_id' => $user->getKey()])->save();

        return $user;
    }
}
