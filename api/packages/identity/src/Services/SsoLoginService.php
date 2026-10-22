<?php

namespace Ulams\Identity\Services;

use Illuminate\Support\Str;
use Ulams\Auth\Exceptions\InvalidReturnUrlException;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Models\LoginHandoffCode;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Auth\Services\LoginHandoffService;
use Ulams\Auth\Support\ReturnUrlPolicy;
use Ulams\Identity\Exceptions\OidcException;
use Ulams\Identity\Exceptions\SsoRefusedException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Oidc\OidcClient;
use Ulams\Identity\Support\Pkce;
use Ulams\Identity\Support\Users;

/**
 * Browser sign-in through a tenant's identity provider (plan 4.3, ADR 0121), on top of auth's
 * {@see LoginHandoffService}: a signed single-use state on the way out, a one-time code bound to the front end's
 * verifier on the way back. The API never puts a token in a URL.
 */
class SsoLoginService
{
    public const PROTOCOL = 'oidc';

    public function __construct(
        private readonly LoginHandoffService $handoff,
        private readonly ReturnUrlPolicy $returnUrls,
        private readonly OidcClient $oidc,
        private readonly AccountResolver $accounts,
        private readonly IdentityAuditLog $audit,
        private readonly AuthServiceContract $auth
    ) {
    }

    /** The enabled OIDC provider of a slug, or null. */
    public function provider(string $slug): ?IdentityProvider
    {
        return IdentityProvider::query()->where('slug', $slug)->where('enabled', true)->where('protocol', IdentityProvider::OIDC)->first();
    }

    /**
     * @return string the provider's authorization URL to redirect the browser to
     * @throws InvalidReturnUrlException
     * @throws \InvalidArgumentException a missing or malformed challenge
     * @throws OidcException the provider could not be reached or is misconfigured
     */
    public function start(IdentityProvider $provider, string $host, string $callbackUrl, ?string $returnTo, ?string $challenge): string
    {
        $returnTo ??= $this->returnUrls->default();
        if (!$this->returnUrls->isAllowed($returnTo)) {
            throw new InvalidReturnUrlException();
        }
        if (!is_string($challenge) || !preg_match(LoginHandoffService::CHALLENGE_PATTERN, $challenge)) {
            throw new \InvalidArgumentException('A challenge (base64url SHA-256 of a verifier) is required.');
        }

        // fail before a state row exists when the provider is down or misconfigured
        $this->oidc->discover($provider);

        $nonce = Pkce::base64Url(random_bytes(32));
        $verifier = Pkce::verifier();
        $state = $this->handoff->startState(self::PROTOCOL, $provider->slug, $host, $returnTo, $challenge, [
            'nonce' => $nonce,
            'code_verifier' => $verifier,
            'redirect_uri' => $callbackUrl,
        ]);

        return $this->oidc->authorizationUrl($provider, $callbackUrl, $state, $nonce, $verifier);
    }

    /**
     * Finishes the sign-in the provider redirected back from. Returns the URL to send the browser to: the
     * allow-listed return URL with `?code=` on success or `?error=<reason>`.
     *
     * @throws LoginHandoffException when the state is not valid: nothing trusted says where to send the browser
     */
    public function handleCallback(string $slug, ?string $state, ?string $code, ?string $providerError, string $host): string
    {
        try {
            $row = $this->handoff->consumeState($state, self::PROTOCOL, $slug, $host);
        } catch (LoginHandoffException $e) {
            // garbage and forged states are noise anybody can send; the rest (expired, replayed, wrong host) is worth a row
            if (!in_array($e->reason, [LoginHandoffException::MALFORMED, LoginHandoffException::BAD_SIGNATURE], true)) {
                $this->failed(IdentityProvider::query()->where('slug', $slug)->first(), $slug, 'state_' . $e->reason);
            }
            throw $e;
        }
        $returnTo = $row->return_to;
        $provider = IdentityProvider::query()->where('slug', $slug)->first();

        if ($provider === null || !$provider->enabled || $provider->protocol !== IdentityProvider::OIDC) {
            $this->failed($provider, $slug, 'provider_unavailable');

            return $this->withQuery($returnTo, ['error' => 'provider_failed']);
        }
        if ($providerError !== null) {
            // `error` and its description come from the provider: only a code of our own goes on
            $this->failed($provider, $slug, 'provider_denied');

            return $this->withQuery($returnTo, ['error' => 'provider_denied']);
        }
        if (!is_string($code) || $code === '' || strlen($code) > 2048) {
            $this->failed($provider, $slug, 'no_code');

            return $this->withQuery($returnTo, ['error' => 'provider_failed']);
        }

        $data = (array) $row->data;
        try {
            $tokens = $this->oidc->exchangeCode($provider, $code, (string) ($data['redirect_uri'] ?? ''), (string) ($data['code_verifier'] ?? ''));
            $claims = $this->oidc->validateIdToken($provider, $tokens['id_token'], (string) ($data['nonce'] ?? ''));
            $claims = $this->oidc->mergeUserInfo($provider, $claims, $tokens['access_token']);
        } catch (OidcException $e) {
            $this->failed($provider, $slug, $e->reason);

            return $this->withQuery($returnTo, ['error' => 'provider_failed']);
        }

        try {
            $result = $this->accounts->resolve($provider, (string) $claims['sub'], $claims);
        } catch (SsoRefusedException $e) {
            $this->failed($provider, $slug, $e->detail !== '' ? $e->detail : $e->reason, ['refused' => $e->reason]);

            return $this->withQuery($returnTo, ['error' => $e->reason]);
        }

        /** @var User $user */
        $user = $result['user'];
        if (!$user->hasVerifiedEmail()) {
            $this->failed($provider, $slug, 'email_unverified', ['user_id' => $user->getKey()]);

            return $this->withQuery($returnTo, ['error' => SsoRefusedException::EMAIL_UNVERIFIED]);
        }

        $provider->forceFill(['last_login_at' => now(), 'last_error' => null])->save();
        if ($result['linked_via'] !== null) {
            $this->audit->record('sso.link', [
                'actor' => 'idp', 'actor_id' => null, 'provider_id' => $provider->getKey(),
                'subject_type' => 'user', 'subject_id' => $user->getKey(), 'data' => ['via' => $result['linked_via'], 'created' => $result['created']],
            ]);
        }
        $this->audit->record('sso.login', [
            'actor' => 'idp', 'actor_id' => null, 'provider_id' => $provider->getKey(),
            'subject_type' => 'user', 'subject_id' => $user->getKey(), 'data' => ['protocol' => 'oidc', 'created' => $result['created']],
        ]);

        $oneTime = $this->handoff->issueCode($row, LoginHandoffCode::KIND_LOGIN, (int) $user->getKey(), null, false);

        return $this->withQuery($returnTo, ['code' => $oneTime, 'complete' => 'true']);
    }

    /**
     * Redeems a one-time code for an access token.
     *
     * @return array{token:\Laravel\Passport\PersonalAccessTokenResult}
     * @throws LoginHandoffException
     */
    public function exchange(string $code, string $verifier, string $host): array
    {
        $row = $this->handoff->redeemCode($code, $verifier, $host);
        $user = $row->kind === LoginHandoffCode::KIND_LOGIN && $row->user_id ? Users::find($row->user_id) : null;
        if (!$user || !$user->hasVerifiedEmail() || !$user->is_active) {
            throw new LoginHandoffException(LoginHandoffException::UNKNOWN);
        }

        return ['token' => $this->auth->createTokenForUser($user, $row->remember_me)];
    }

    /** @param array<string,mixed> $extra */
    private function failed(?IdentityProvider $provider, string $slug, string $reason, array $extra = []): void
    {
        $provider?->forceFill(['last_error' => Str::limit($reason, 250, '')])->save();
        $this->audit->record('sso.login_failed', [
            'actor' => 'idp', 'actor_id' => null, 'provider_id' => $provider?->getKey(),
            'data' => ['reason' => $reason, 'slug' => Str::limit($slug, 64, '')] + $extra,
        ]);
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
}
