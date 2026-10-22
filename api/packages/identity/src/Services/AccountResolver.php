<?php

namespace Ulams\Identity\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Identity\Exceptions\SsoRefusedException;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Support\Users;

/**
 * Turns a verified identity (provider + subject + claims) into a local user (plan 4.5, ADR 0121).
 *
 * 1. A link for (provider, subject) exists: that user, whatever e-mail the provider sends now.
 * 2. No link and no local user with that e-mail: create a student (when the provider allows it) with a
 *    verified address, but only if the address is vouched for (`email_verified`, or the provider is trusted).
 * 3. No link and a local user with that e-mail: link it only when the provider is trusted for the address's
 *    domain, the provider says the address is verified, and the account is not privileged (admin, tutor,
 *    superadmin). Otherwise refuse: the owner confirms with the password (later milestone) or an admin links.
 * 4. Inactive and deleted accounts are refused.
 */
class AccountResolver
{
    public function __construct(private readonly UserServiceContract $users)
    {
    }

    /**
     * @param array<string,mixed> $claims
     * @return array{user:User,created:bool,linked_via:?string}
     * @throws SsoRefusedException
     */
    public function resolve(IdentityProvider $provider, string $subject, array $claims, int $attempt = 0): array
    {
        $link = IdentityLink::query()->where('provider_id', $provider->getKey())->where('subject', $subject)->first();
        if ($link !== null) {
            $user = Users::withTrashed()->find($link->user_id);
            $this->assertUsable($user);

            return ['user' => $user, 'created' => false, 'linked_via' => null];
        }

        $email = $this->email($claims);
        if ($email === null) {
            throw new SsoRefusedException(SsoRefusedException::EMAIL_REQUIRED, 'no_email_claim');
        }
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? null) === 'true';

        $existing = Users::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null) {
            $this->assertUsable($existing);
            if (!$this->mayLinkByEmail($provider, $existing, $email, $verified)) {
                throw new SsoRefusedException(SsoRefusedException::LINK_REQUIRED, $verified ? 'not_trusted_for_domain' : 'email_not_verified_by_provider');
            }
            $this->claim($existing);
            $this->link($provider, $existing, $subject, $email, IdentityLink::EMAIL_TRUST);

            return ['user' => $existing, 'created' => false, 'linked_via' => IdentityLink::EMAIL_TRUST];
        }

        if (!$provider->jit_enabled) {
            throw new SsoRefusedException(SsoRefusedException::SIGNUP_DISABLED, 'jit_disabled');
        }
        if (!$verified && !$provider->trust_email) {
            // an address the provider did not vouch for would let anybody claim it before its owner registers
            throw new SsoRefusedException(SsoRefusedException::EMAIL_UNVERIFIED, 'email_not_verified_by_provider');
        }

        try {
            $user = DB::transaction(function () use ($provider, $claims, $email, $subject) {
                [$first, $last] = $this->names($claims, $email);
                // no password: the person signs in through the provider, or sets one with "forgot password"
                $user = $this->users->create(new UserSaveDto($first, $last, true, [$this->role($provider)], $email, null, null, true));
                $this->link($provider, $user, $subject, $email, IdentityLink::JIT);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            // a parallel first sign-in of the same person or address won the race: try again from the top, once
            if ($attempt > 0) {
                throw new SsoRefusedException(SsoRefusedException::ACCOUNT_UNAVAILABLE, 'race_unresolved');
            }

            return $this->resolve($provider, $subject, $claims, 1);
        }

        return ['user' => $user, 'created' => true, 'linked_via' => IdentityLink::JIT];
    }

    private function assertUsable(?User $user): void
    {
        if ($user === null || $user->trashed() || !$user->is_active) {
            throw new SsoRefusedException(SsoRefusedException::ACCOUNT_UNAVAILABLE, $user === null ? 'user_missing' : ($user->trashed() ? 'user_deleted' : 'user_inactive'));
        }
    }

    private function mayLinkByEmail(IdentityProvider $provider, User $existing, string $email, bool $providerVerified): bool
    {
        if (!$provider->trust_email || !$providerVerified || !$provider->ownsDomainOf($email)) {
            return false;
        }
        $superadmins = array_map('strtolower', array_filter((array) config(UlamsAuthServiceProvider::CONFIG_KEY . '.superadmins', [])));
        if (in_array($email, $superadmins, true)) {
            return false;
        }

        return !$existing->hasAnyRole([UserRole::ADMIN, UserRole::TUTOR]);
    }

    /**
     * The provider proved the address belongs to the person signing in. If the local account never confirmed it,
     * somebody else may have registered it with a password of their own (pre-hijacking): drop that password and
     * every token before the real owner takes over.
     */
    private function claim(User $user): void
    {
        if (!$user->hasVerifiedEmail()) {
            $user->forceFill(['password' => null])->save();
            $user->tokens()->update(['revoked' => true]);
            $user->markEmailAsVerified();
            event(new AccountConfirmed($user));
        }
    }

    private function link(IdentityProvider $provider, User $user, string $subject, string $email, string $via): IdentityLink
    {
        return IdentityLink::query()->create([
            'provider_id' => $provider->getKey(),
            'user_id' => $user->getKey(),
            'subject' => $subject,
            'email_at_link' => $email,
            'linked_via' => $via,
        ]);
    }

    private function role(IdentityProvider $provider): string
    {
        // never admin from a provider setting; the admin API only accepts these two
        return $provider->default_role === UserRole::TUTOR ? UserRole::TUTOR : UserRole::STUDENT;
    }

    /** @param array<string,mixed> $claims */
    private function email(array $claims): ?string
    {
        $email = $claims['email'] ?? null;
        if (!is_string($email)) {
            return null;
        }
        $email = Str::lower(trim($email));

        return $email !== '' && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param array<string,mixed> $claims
     * @return array{0:string,1:string}
     */
    private function names(array $claims, string $email): array
    {
        $clean = static fn (mixed $v): string => is_string($v) ? trim(mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', $v) ?? '', 0, 120)) : '';
        $first = $clean($claims['given_name'] ?? null);
        $last = $clean($claims['family_name'] ?? null);
        if ($first === '' && $last === '') {
            $name = $clean($claims['name'] ?? null);
            $first = Str::before($name, ' ');
            $last = trim(Str::after($name, ' ') === $name ? '' : Str::after($name, ' '));
        }
        if ($first === '') {
            $first = Str::before($email, '@');
        }

        return [$first, $last];
    }
}
