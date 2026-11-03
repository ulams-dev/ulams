<?php

namespace Ulams\Auth\Services;

use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Exceptions\TokenExpiredException;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Models\LoginHandoffCode;
use Ulams\Auth\Support\ReturnUrlPolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;
use Ulams\Auth\Models\PreUser;
use Ulams\Auth\Models\User;
use Ulams\Auth\Repositories\Contracts\PreUserRepositoryContract;
use Ulams\Auth\Repositories\Contracts\SocialAccountRepositoryContract;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Auth\Services\Contracts\SocialAccountServiceContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Core\Enums\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;

class SocialAccountService implements SocialAccountServiceContract
{
    private const TOKEN_EXPIRATION_TIME = 10;
    private const PROTOCOL = 'social';

    private AuthServiceContract $authService;
    private UserServiceContract $userService;
    private SocialAccountRepositoryContract $socialAccountRepository;
    private UserRepositoryContract $userRepository;
    private PreUserRepositoryContract $preUserRepository;
    private LoginHandoffService $handoff;
    private ReturnUrlPolicy $returnUrls;

    public function __construct(
        AuthServiceContract $authService,
        UserServiceContract $userService,
        SocialAccountRepositoryContract $socialAccountRepository,
        UserRepositoryContract $userRepository,
        PreUserRepositoryContract $preUserRepository,
        LoginHandoffService $handoff,
        ReturnUrlPolicy $returnUrls
    ) {
        $this->authService = $authService;
        $this->userService = $userService;
        $this->socialAccountRepository = $socialAccountRepository;
        $this->userRepository = $userRepository;
        $this->preUserRepository = $preUserRepository;
        $this->handoff = $handoff;
        $this->returnUrls = $returnUrls;
    }

    public function startLogin(string $provider, string $host, ?string $returnUrl, string $challenge): string
    {
        $returnTo = $returnUrl ?? $this->returnUrls->default();
        if (!$this->returnUrls->isAllowed($returnTo)) {
            throw new InvalidReturnUrlException();
        }

        return $this->handoff->startState(self::PROTOCOL, $provider, $host, $returnTo, $challenge);
    }

    public function handleCallback(string $provider, ?string $state, string $host, ?string $providerError = null): string
    {
        $handoffState = $this->handoff->consumeState($state, self::PROTOCOL, $provider, $host);
        $returnTo = $handoffState->return_to;

        if ($providerError !== null) {
            return $this->redirectWithError($returnTo, 'provider_denied');
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (Throwable $e) {
            report($e);

            return $this->redirectWithError($returnTo, 'provider_failed');
        }

        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail() ? Str::lower(trim($socialUser->getEmail())) : null;
        $verified = $this->providerVerifiesEmail($socialUser);

        $user = null;
        $socialAccount = $this->socialAccountRepository->findByProviderAndProviderId($provider, $providerId);
        if ($socialAccount) {
            $user = $socialAccount->user;
        } elseif ($email) {
            $existing = $this->userRepository->findByEmail($email);
            if ($existing) {
                if (!$this->mayLinkByEmail($provider, $existing, $email, $verified)) {
                    return $this->redirectWithError($returnTo, 'link_required');
                }
                $user = $this->claimExistingUser($existing, $verified);
            } else {
                $user = $this->createUser($socialUser, $email, $verified);
            }
            $this->linkSocialAccount($user, $provider, $providerId);
        }

        if ($user) {
            if (!$user->hasVerifiedEmail()) {
                // never vouch for an address the provider did not vouch for: the person confirms it by e-mail
                event(new AccountRegistered($user, $returnTo));

                return $this->redirectWithError($returnTo, 'email_unverified');
            }
            if (!$user->is_active) {
                return $this->redirectWithError($returnTo, 'account_unavailable');
            }

            $code = $this->handoff->issueCode(
                $handoffState,
                LoginHandoffCode::KIND_LOGIN,
                (int) $user->getKey(),
                null,
                (bool) Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.socialite_remember_me', false)
            );

            return $this->redirectWithCode($returnTo, $code, true);
        }

        $preUser = $this->createPreUser($provider, $socialUser);
        $code = $this->handoff->issueCode($handoffState, LoginHandoffCode::KIND_COMPLETE, null, $preUser->token);

        return $this->redirectWithCode($returnTo, $code, false);
    }

    public function exchange(string $code, string $verifier, string $host): array
    {
        $row = $this->handoff->redeemCode($code, $verifier, $host);

        if ($row->kind === LoginHandoffCode::KIND_COMPLETE) {
            return ['complete' => false, 'pre_user_token' => $row->payload];
        }

        /** @var User|null $user */
        $user = $row->user_id ? $this->userRepository->find($row->user_id) : null;
        if (!$user || !$this->isUserActiveAndVerified($user)) {
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }

        $token = $this->authService->createTokenForUser($user, $row->remember_me);

        return ['complete' => true, 'token' => $token];
    }

    /**
     * @throws TokenExpiredException
     */
    public function completeData(string $token, string $email, ?string $returnUrl): void
    {
        $preUser = $this->preUserRepository->findByToken($token);

        if ($preUser->created_at <= Carbon::now()->subMinutes(self::TOKEN_EXPIRATION_TIME)) {
            throw new TokenExpiredException();
        }

        // the address was typed by whoever holds the pre-user token: it is confirmed by e-mail, not assumed
        $userSaveDto = new UserSaveDto(
            $preUser->first_name,
            $preUser->last_name,
            true,
            [UserRole::STUDENT],
            $email,
            null,
            null,
            false,
        );

        $user = $this->userService->create($userSaveDto);
        event(new AccountRegistered($user, $returnUrl));

        $this->socialAccountRepository->create([
            'user_id' => $user->getKey(),
            'provider' => $preUser->provider,
            'provider_id' => $preUser->provider_id,
        ]);

        $preUser->delete();
    }

    private function createUser(AbstractUser $socialUser, string $email, bool $verified): User
    {
        [$firstName, $lastName] = $this->splitSocialName($socialUser);

        // no password: a social account signs in through its provider, or sets one with "forgot password"
        $userSaveDto = new UserSaveDto(
            $firstName,
            $lastName,
            true,
            [UserRole::STUDENT],
            $email,
            null,
            null,
            $verified,
        );

        return $this->userService->create($userSaveDto);
    }

    /**
     * An existing local account is linked by e-mail only when the provider vouches for the address
     * (`email_verified`), the domain is one the provider is authoritative for
     * (`ulams_auth.social_login.trusted_domains`) and the account is not privileged. Everything
     * else needs the owner to sign in with the password (or an admin to link).
     */
    private function mayLinkByEmail(string $provider, User $existing, string $email, bool $providerVerified): bool
    {
        if (!$providerVerified || Str::lower((string) $existing->email) !== $email) {
            return false;
        }
        $domain = Str::after($email, '@');
        $trusted = array_map('strtolower', (array) Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.social_login.trusted_domains.' . $provider, []));
        if (!in_array($domain, $trusted, true)) {
            return false;
        }
        if (in_array($email, array_map('strtolower', array_filter((array) Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.superadmins', []))), true)) {
            return false;
        }

        return !$existing->hasAnyRole([UserRole::ADMIN, UserRole::TUTOR]);
    }

    /**
     * The provider proved the e-mail address belongs to the person who is signing in. If the local
     * account never confirmed it, somebody else may have registered it with a password of their
     * own (pre-hijacking): drop that password and every token before the real owner takes over.
     */
    private function claimExistingUser(User $user, bool $providerVerified): User
    {
        if (!$user->hasVerifiedEmail() && $providerVerified) {
            $user->forceFill(['password' => null])->save();
            $user->tokens()->update(['revoked' => true]);
            $user->markEmailAsVerified();
            event(new AccountConfirmed($user));
        }

        return $user;
    }

    private function linkSocialAccount(User $user, string $provider, string $providerId): void
    {
        try {
            $this->socialAccountRepository->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_id' => $providerId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // a parallel callback of the same identity won the race: it is linked already
        }
    }

    private function providerVerifiesEmail(AbstractUser $socialUser): bool
    {
        $raw = is_array($socialUser->user ?? null) ? $socialUser->user : [];
        $value = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function createPreUser(string $provider, AbstractUser $socialUser): PreUser
    {
        [$firstName, $lastName] = $this->splitSocialName($socialUser);

        /** @var PreUser */
        return $this->preUserRepository->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'token' => Str::random(32),
        ]);
    }

    private function splitSocialName(AbstractUser $socialUser): array
    {
        $name = Str::of($socialUser->getName());
        $firstName = Str::of($name->explode(' ')->first())->trim();
        $lastName = $name->after($firstName)->trim();

        return [$firstName, $lastName];
    }

    private function redirectWithCode(string $returnTo, string $code, bool $complete): string
    {
        return $this->withQuery($returnTo, ['code' => $code, 'complete' => $complete ? 'true' : 'false']);
    }

    private function redirectWithError(string $returnTo, string $reason): string
    {
        return $this->withQuery($returnTo, ['error' => $reason]);
    }

    /** Adds to the query of an allow-listed URL, keeping its fragment (hash routers). */
    private function withQuery(string $url, array $params): string
    {
        $fragment = '';
        if (($pos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params) . $fragment;
    }

    private function isUserActiveAndVerified(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->is_active;
    }
}
