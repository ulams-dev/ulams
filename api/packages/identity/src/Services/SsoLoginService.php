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
use Ulams\Identity\Exceptions\SamlException;
use Ulams\Identity\Exceptions\SsoRefusedException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Models\IdentitySession;
use Ulams\Identity\Services\Oidc\OidcClient;
use Ulams\Identity\Services\Saml\SamlAssertion;
use Ulams\Identity\Services\Saml\SamlContext;
use Ulams\Identity\Services\Saml\SamlService;
use Ulams\Identity\Support\Pkce;
use Ulams\Identity\Support\Users;

/**
 * Browser sign-in through a tenant's identity provider (OpenID Connect and SAML 2.0, plan 4.3, ADR 0121), on top of auth's
 * {@see LoginHandoffService}: a signed single-use state on the way out, a one-time code bound to the front end's
 * verifier on the way back. The API never puts a token in a URL.
 */
class SsoLoginService
{
    public const PROTOCOL = 'oidc';
    public const SAML_PROTOCOL = 'saml';

    public function __construct(
        private readonly LoginHandoffService $handoff,
        private readonly ReturnUrlPolicy $returnUrls,
        private readonly OidcClient $oidc,
        private readonly SamlService $saml,
        private readonly AccountResolver $accounts,
        private readonly IdentityAuditLog $audit,
        private readonly AuthServiceContract $auth
    ) {
    }

    /** The enabled provider of a slug (any protocol), or null. */
    public function provider(string $slug): ?IdentityProvider
    {
        return IdentityProvider::query()->where('slug', $slug)->where('enabled', true)->first();
    }

    /**
     * @return string the provider's authorization URL to redirect the browser to
     * @throws InvalidReturnUrlException
     * @throws \InvalidArgumentException a missing or malformed challenge
     * @throws OidcException the provider could not be reached or is misconfigured
     */
    public function start(IdentityProvider $provider, string $host, string $callbackUrl, ?string $returnTo, ?string $challenge): string
    {
        $returnTo = $this->checkStart($returnTo, $challenge);

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
     * Starts a SAML sign-in: a signed AuthnRequest over the redirect binding, with the login state as RelayState.
     *
     * @return string the IdP's single sign-on URL with the request to redirect the browser to
     * @throws InvalidReturnUrlException
     * @throws \InvalidArgumentException a missing or malformed challenge
     * @throws SamlException the provider is not configured for SAML login
     */
    public function startSaml(IdentityProvider $provider, SamlContext $ctx, ?string $returnTo, ?string $challenge): string
    {
        $returnTo = $this->checkStart($returnTo, $challenge);
        $login = $this->saml->beginLogin($provider, $ctx);
        $state = $this->handoff->startState(self::SAML_PROTOCOL, $provider->slug, $ctx->host, $returnTo, (string) $challenge, ['request_id' => $login['id']]);

        return $login['redirect']($state);
    }

    /** @return string the return URL, defaulted */
    private function checkStart(?string $returnTo, ?string $challenge): string
    {
        $returnTo ??= $this->returnUrls->default();
        if (!$this->returnUrls->isAllowed($returnTo)) {
            throw new InvalidReturnUrlException();
        }
        if (!is_string($challenge) || !preg_match(LoginHandoffService::CHALLENGE_PATTERN, $challenge)) {
            throw new \InvalidArgumentException('A challenge (base64url SHA-256 of a verifier) is required.');
        }

        return $returnTo;
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

        return $this->complete($provider, $row, (string) $claims['sub'], $claims, 'oidc', null);
    }

    /**
     * Finishes a SAML sign-in: the browser posted the IdP's response to the ACS. Returns the URL to send it to: the
     * allow-listed return URL with `?code=` on success or `?error=<reason>`.
     *
     * @throws LoginHandoffException when the state is not valid: nothing trusted says where to send the browser
     */
    public function handleAcs(string $slug, SamlContext $ctx, ?string $relayState, ?string $samlResponse): string
    {
        try {
            $row = $this->handoff->consumeState($relayState, self::SAML_PROTOCOL, $slug, $ctx->host);
        } catch (LoginHandoffException $e) {
            if (!in_array($e->reason, [LoginHandoffException::MALFORMED, LoginHandoffException::BAD_SIGNATURE], true)) {
                $this->failed(IdentityProvider::query()->where('slug', $slug)->first(), $slug, 'state_' . $e->reason);
            }
            throw $e;
        }
        $returnTo = $row->return_to;
        $provider = IdentityProvider::query()->where('slug', $slug)->first();

        if ($provider === null || !$provider->enabled || $provider->protocol !== IdentityProvider::SAML) {
            $this->failed($provider, $slug, 'provider_unavailable');

            return $this->withQuery($returnTo, ['error' => 'provider_failed']);
        }

        try {
            $assertion = $this->saml->validateResponse($provider, $ctx, $samlResponse, (string) (((array) $row->data)['request_id'] ?? ''));
        } catch (SamlException $e) {
            $this->failed($provider, $slug, $e->reason);

            return $this->withQuery($returnTo, ['error' => 'provider_failed']);
        }

        $subject = $this->samlSubject($provider, $assertion);
        if ($subject === null) {
            $this->failed($provider, $slug, SamlException::TRANSIENT_NAME_ID);

            return $this->withQuery($returnTo, ['error' => 'provider_failed']);
        }
        if ($assertion->usedSha1) {
            // the provider opted in to SHA-1 (legacy ADFS): accepted, but every such sign-in is on the record
            $this->audit->record('saml.sha1_accepted', ['actor' => 'idp', 'actor_id' => null, 'provider_id' => $provider->getKey(), 'data' => ['slug' => $provider->slug]]);
        }

        return $this->complete($provider, $row, $subject, $this->samlClaims($provider, $assertion), 'saml', [
            'n' => $assertion->nameId, 'f' => $assertion->nameIdFormat, 's' => $assertion->sessionIndex,
        ]);
    }

    /**
     * Resolves the account for a validated identity and hands the browser the one-time code.
     *
     * @param array<string,mixed> $claims
     * @param array<string,mixed>|null $session SAML session data kept for single logout
     */
    private function complete(IdentityProvider $provider, \Ulams\Auth\Models\LoginHandoffState $row, string $subject, array $claims, string $protocol, ?array $session): string
    {
        $slug = $provider->slug;
        $returnTo = $row->return_to;
        try {
            $result = $this->accounts->resolve($provider, $subject, $claims);
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
            'subject_type' => 'user', 'subject_id' => $user->getKey(), 'data' => ['protocol' => $protocol, 'created' => $result['created']],
        ]);

        $payload = $session === null ? null : json_encode(['p' => $provider->getKey()] + $session, JSON_UNESCAPED_SLASHES);
        $oneTime = $this->handoff->issueCode($row, LoginHandoffCode::KIND_LOGIN, (int) $user->getKey(), $payload, false);

        return $this->withQuery($returnTo, ['code' => $oneTime, 'complete' => 'true']);
    }

    /**
     * The stable subject of a SAML identity: the NameID, unless it is transient (a different value every login),
     * in which case the provider must name the attribute that carries the identity.
     */
    private function samlSubject(IdentityProvider $provider, SamlAssertion $assertion): ?string
    {
        $configured = (string) (((array) (((array) $provider->saml)['attribute_map'] ?? []))['subject'] ?? '');
        if ($configured !== '') {
            return $assertion->first($configured);
        }

        return $assertion->nameIdFormat === \OneLogin\Saml2\Constants::NAMEID_TRANSIENT ? null : $assertion->nameId;
    }

    /**
     * The claims the account resolver understands, from the assertion's attributes. Providers can name their own
     * attributes in `saml.attribute_map` (email, first_name, last_name); the usual ones are tried otherwise. A SAML
     * assertion does not say an address is verified: the provider's `trust_email` switch does.
     *
     * @return array<string,mixed>
     */
    private function samlClaims(IdentityProvider $provider, SamlAssertion $assertion): array
    {
        $map = (array) (((array) $provider->saml)['attribute_map'] ?? []);
        $pick = static fn (string $key, array $defaults): ?string => $assertion->first(...array_values(array_filter([(string) ($map[$key] ?? ''), ...$defaults])));

        $email = $pick('email', [
            'email', 'mail', 'emailAddress', 'urn:oid:0.9.2342.19200300.100.1.3',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        ]);
        if ($email === null && $assertion->nameIdFormat === \OneLogin\Saml2\Constants::NAMEID_EMAIL_ADDRESS) {
            $email = $assertion->nameId;
        }

        return [
            'sub' => $this->samlSubject($provider, $assertion),
            'email' => $email,
            'email_verified' => (bool) $provider->trust_email,
            'given_name' => $pick('first_name', ['givenName', 'givenname', 'firstName', 'urn:oid:2.5.4.42', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname']),
            'family_name' => $pick('last_name', ['sn', 'surname', 'lastName', 'urn:oid:2.5.4.4', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname']),
            'name' => $pick('name', ['displayName', 'name', 'cn', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name']),
        ];
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

        $token = $this->auth->createTokenForUser($user, $row->remember_me);
        $this->rememberSession($row, $user, (string) $token->token->getKey());

        return ['token' => $token];
    }

    /** Keeps what single logout needs to find this login's token again (SAML only). */
    private function rememberSession(LoginHandoffCode $row, User $user, string $tokenId): void
    {
        $data = is_string($row->payload) && $row->payload !== '' ? json_decode($row->payload, true) : null;
        if (!is_array($data) || !isset($data['p'], $data['n']) || !is_string($data['n']) || $data['n'] === '') {
            return;
        }
        if (!IdentityProvider::query()->whereKey($data['p'])->exists()) {
            return;
        }
        IdentitySession::query()->create([
            'provider_id' => $data['p'],
            'user_id' => $user->getKey(),
            'token_id' => $tokenId,
            'name_id' => $data['n'],
            'name_id_format' => is_string($data['f'] ?? null) ? $data['f'] : null,
            'session_index' => is_string($data['s'] ?? null) ? $data['s'] : null,
            'created_at' => now(),
        ]);
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
