<?php

namespace Ulams\Identity\Services\Oidc;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Ulams\Core\Http\SafeHttp;
use Ulams\Identity\Exceptions\OidcException;
use Ulams\Identity\Models\IdentityProvider;

/**
 * A small OpenID Connect relying party (ADR 0120): discovery, authorization code with PKCE, ID token
 * validation, optional userinfo. No library beyond firebase/php-jwt for the signature; every call to the
 * provider goes through {@see SafeHttp}.
 *
 * ID token rules: the algorithm must be on the allow-list (no `none`, no HMAC: a public key must never
 * become a shared secret), the key comes from the provider's JWKS and must fit the algorithm family,
 * `iss` equals the issuer exactly, `aud` contains our client id (and `azp` equals it when there are
 * several audiences), `exp` is required, `iat` and `nbf` are checked with a small leeway, the `nonce`
 * is the one stored with the login state, and `sub` is present.
 */
class OidcClient
{
    /** @var list<string> */
    public const ALGORITHMS = ['RS256', 'RS384', 'RS512', 'PS256', 'ES256', 'ES384'];

    /** What a failed ID token check maps to, by the exceptions of firebase/php-jwt. */
    private const RSA_ALGS = ['RS256', 'RS384', 'RS512', 'PS256'];
    private const EC_ALGS = ['ES256', 'ES384'];

    /**
     * The provider's discovery document, validated: issuer exactly as configured, https endpoints,
     * PKCE S256 when the provider says which methods it supports.
     *
     * @return array{issuer:string,authorization_endpoint:string,token_endpoint:string,jwks_uri:string,userinfo_endpoint?:string}
     * @throws OidcException
     */
    public function discover(IdentityProvider $provider, bool $refresh = false): array
    {
        $issuer = $this->issuer($provider);
        $key = 'identity:oidc:discovery:' . $provider->getKey() . ':' . sha1($issuer);
        if ($refresh) {
            Cache::forget($key);
        }
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        $document = $this->getJson($url, [], OidcException::DISCOVERY_FAILED);

        if (($document['issuer'] ?? null) !== $issuer) {
            throw new OidcException(OidcException::ISSUER_MISMATCH);
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $endpoint) {
            if (!is_string($document[$endpoint] ?? null) || !$this->endpointAllowed($document[$endpoint])) {
                throw new OidcException(OidcException::INSECURE_ENDPOINT);
            }
        }
        if (isset($document['userinfo_endpoint']) && (!is_string($document['userinfo_endpoint']) || !$this->endpointAllowed($document['userinfo_endpoint']))) {
            unset($document['userinfo_endpoint']);
        }
        if (isset($document['code_challenge_methods_supported']) && !in_array('S256', (array) $document['code_challenge_methods_supported'], true)) {
            throw new OidcException(OidcException::DISCOVERY_FAILED);
        }

        $keep = array_intersect_key($document, array_flip(['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint']));
        Cache::put($key, $keep, (int) config('ulams_identity.oidc.discovery_ttl'));

        return $keep;
    }

    /** @throws OidcException */
    public function authorizationUrl(IdentityProvider $provider, string $redirectUri, string $state, string $nonce, string $codeVerifier): string
    {
        $document = $this->discover($provider);
        $oidc = (array) $provider->oidc;
        $scopes = array_values(array_unique(array_merge(['openid'], array_map('strval', (array) ($oidc['scopes'] ?? ['email', 'profile'])))));

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) ($oidc['client_id'] ?? ''),
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => \Ulams\Identity\Support\Pkce::challenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        $endpoint = $document['authorization_endpoint'];

        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . $query;
    }

    /**
     * Redeems the authorization code.
     *
     * @return array{id_token:string,access_token:?string}
     * @throws OidcException
     */
    public function exchangeCode(IdentityProvider $provider, string $code, string $redirectUri, string $codeVerifier): array
    {
        $document = $this->discover($provider);
        $oidc = (array) $provider->oidc;
        $clientId = (string) ($oidc['client_id'] ?? '');
        $secret = (string) $provider->oidc_client_secret;

        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ];
        $headers = ['Accept' => 'application/json'];
        if (($oidc['token_auth'] ?? 'client_secret_basic') === 'client_secret_post') {
            $form['client_id'] = $clientId;
            $form['client_secret'] = $secret;
        } else {
            // RFC 6749 2.3.1: the id and the secret are form-encoded before they are base64-encoded
            $headers['Authorization'] = 'Basic ' . base64_encode(urlencode($clientId) . ':' . urlencode($secret));
        }

        try {
            $response = $this->http()->post($document['token_endpoint'], ['form_params' => $form, 'headers' => $headers, 'http_errors' => false]);
        } catch (OidcException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new OidcException(OidcException::TOKEN_REQUEST_FAILED);
        }
        if ($response->getStatusCode() !== 200) {
            throw new OidcException(OidcException::TOKEN_REQUEST_FAILED);
        }
        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body) || !is_string($body['id_token'] ?? null) || $body['id_token'] === '') {
            throw new OidcException(OidcException::NO_ID_TOKEN);
        }

        return [
            'id_token' => $body['id_token'],
            'access_token' => is_string($body['access_token'] ?? null) ? $body['access_token'] : null,
        ];
    }

    /**
     * Verifies the signature and the claims of an ID token.
     *
     * @return array<string,mixed> the claims
     * @throws OidcException
     */
    public function validateIdToken(IdentityProvider $provider, string $idToken, string $expectedNonce): array
    {
        $document = $this->discover($provider);
        $segments = explode('.', $idToken);
        if (count($segments) !== 3 || strlen($idToken) > 16384) {
            throw new OidcException(OidcException::MALFORMED);
        }
        $header = json_decode((string) JWT::urlsafeB64Decode($segments[0]), true);
        if (!is_array($header)) {
            throw new OidcException(OidcException::MALFORMED);
        }
        $alg = $header['alg'] ?? null;
        if (!is_string($alg) || !in_array($alg, self::ALGORITHMS, true)) {
            throw new OidcException(OidcException::BAD_ALGORITHM);
        }
        $kid = isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : null;

        $key = $this->signingKey($provider, $document['jwks_uri'], $alg, $kid);

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = (int) config('ulams_identity.oidc.leeway');
        try {
            $claims = (array) JWT::decode($idToken, $key);
        } catch (SignatureInvalidException) {
            throw new OidcException(OidcException::BAD_SIGNATURE);
        } catch (ExpiredException) {
            throw new OidcException(OidcException::EXPIRED);
        } catch (BeforeValidException) {
            throw new OidcException(OidcException::NOT_YET_VALID);
        } catch (Throwable) {
            throw new OidcException(OidcException::MALFORMED);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $this->checkClaims($provider, $claims, $expectedNonce);

        return $claims;
    }

    /**
     * Merges the userinfo claims into the ID token claims (the ID token wins); the userinfo `sub` must be the ID token's.
     *
     * @param array<string,mixed> $claims
     * @return array<string,mixed>
     * @throws OidcException
     */
    public function mergeUserInfo(IdentityProvider $provider, array $claims, ?string $accessToken): array
    {
        if (!($provider->oidc['userinfo'] ?? false)) {
            return $claims;
        }
        $endpoint = $this->discover($provider)['userinfo_endpoint'] ?? null;
        if ($endpoint === null || $accessToken === null) {
            return $claims;
        }
        $info = $this->getJson($endpoint, ['Authorization' => 'Bearer ' . $accessToken], OidcException::USERINFO_FAILED);
        if (($info['sub'] ?? null) !== $claims['sub']) {
            throw new OidcException(OidcException::SUBJECT_MISMATCH);
        }

        return $claims + $info;
    }

    /** @param array<string,mixed> $claims */
    private function checkClaims(IdentityProvider $provider, array $claims, string $expectedNonce): void
    {
        $oidc = (array) $provider->oidc;
        if (!isset($claims['exp'])) {
            throw new OidcException(OidcException::MISSING_CLAIM);
        }
        if (($claims['iss'] ?? null) !== $this->issuer($provider)) {
            throw new OidcException(OidcException::WRONG_ISSUER);
        }
        $clientId = (string) ($oidc['client_id'] ?? '');
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : (is_string($audience) ? [$audience] : []);
        if ($clientId === '' || !in_array($clientId, $audiences, true)) {
            throw new OidcException(OidcException::WRONG_AUDIENCE);
        }
        if (count($audiences) > 1 && ($claims['azp'] ?? null) !== $clientId) {
            throw new OidcException(OidcException::WRONG_AUTHORIZED_PARTY);
        }
        if (isset($claims['azp']) && $claims['azp'] !== $clientId) {
            throw new OidcException(OidcException::WRONG_AUTHORIZED_PARTY);
        }
        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || $expectedNonce === '' || !hash_equals($expectedNonce, $nonce)) {
            throw new OidcException(OidcException::NONCE_MISMATCH);
        }
        $subject = $claims['sub'] ?? null;
        if (!is_string($subject) || $subject === '' || strlen($subject) > 255) {
            throw new OidcException(OidcException::MISSING_CLAIM);
        }
    }

    /** @throws OidcException */
    private function signingKey(IdentityProvider $provider, string $jwksUri, string $alg, ?string $kid): \Firebase\JWT\Key
    {
        foreach ([false, true] as $refresh) {
            $jwk = $this->findJwk($provider, $jwksUri, $kid, $alg, $refresh);
            if ($jwk !== null) {
                break;
            }
        }
        if ($jwk === null) {
            throw new OidcException(OidcException::UNKNOWN_KEY);
        }

        $kty = $jwk['kty'] ?? null;
        $fits = ($kty === 'RSA' && in_array($alg, self::RSA_ALGS, true)) || ($kty === 'EC' && in_array($alg, self::EC_ALGS, true));
        if (!$fits || (isset($jwk['alg']) && $jwk['alg'] !== $alg) || (isset($jwk['use']) && $jwk['use'] !== 'sig')) {
            throw new OidcException(OidcException::BAD_ALGORITHM);
        }
        try {
            $key = JWK::parseKey($jwk, $alg);
        } catch (Throwable) {
            throw new OidcException(OidcException::JWKS_FAILED);
        }
        if ($key === null) {
            throw new OidcException(OidcException::UNKNOWN_KEY);
        }

        return $key;
    }

    /**
     * @return array<string,mixed>|null
     * @throws OidcException
     */
    private function findJwk(IdentityProvider $provider, string $jwksUri, ?string $kid, string $alg, bool $refresh): ?array
    {
        $cacheKey = 'identity:oidc:jwks:' . $provider->getKey() . ':' . sha1($jwksUri);
        if ($refresh) {
            // an unknown kid refetches once: the provider may have rotated its keys
            Cache::forget($cacheKey);
        }
        $set = Cache::get($cacheKey);
        $fresh = false;
        if (!is_array($set)) {
            $set = $this->getJson($jwksUri, [], OidcException::JWKS_FAILED);
            $fresh = true;
        }
        $keys = array_values(array_filter((array) ($set['keys'] ?? []), 'is_array'));
        if ($fresh) {
            Cache::put($cacheKey, ['keys' => $keys], (int) config('ulams_identity.oidc.jwks_ttl'));
        }

        $candidates = $kid !== null
            ? array_values(array_filter($keys, fn (array $k) => ($k['kid'] ?? null) === $kid))
            : (count($keys) === 1 ? $keys : []);

        return $candidates[0] ?? null;
    }

    private function issuer(IdentityProvider $provider): string
    {
        $issuer = (string) ($provider->oidc['issuer'] ?? '');
        if ($issuer === '') {
            throw new OidcException(OidcException::DISCOVERY_FAILED);
        }

        return $issuer;
    }

    private function endpointAllowed(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https' || ($scheme === 'http' && (bool) config('ulams_identity.allow_insecure_urls'));
    }

    /**
     * @param array<string,string> $headers
     * @return array<string,mixed>
     * @throws OidcException
     */
    private function getJson(string $url, array $headers, string $failure): array
    {
        try {
            $response = $this->http()->get($url, ['headers' => $headers + ['Accept' => 'application/json'], 'http_errors' => false]);
        } catch (OidcException $e) {
            throw $e;
        } catch (GuzzleException|Throwable) {
            throw new OidcException($failure);
        }
        $body = $response->getStatusCode() === 200 ? json_decode((string) $response->getBody(), true) : null;
        if (!is_array($body)) {
            throw new OidcException($failure);
        }

        return $body;
    }

    private function http(): Client
    {
        $handler = app()->bound('identity.http_handler') ? app('identity.http_handler') : null;
        $insecure = (bool) config('ulams_identity.allow_insecure_urls');

        return SafeHttp::client($handler, [
            'allow_insecure' => $insecure,
            'max_bytes' => (int) config('ulams_identity.oidc.max_response_bytes'),
            'timeout' => (int) config('ulams_identity.oidc.timeout'),
            'label' => 'identity provider URLs',
            'exception' => static fn (string $message): Throwable => new OidcException(OidcException::INSECURE_ENDPOINT),
        ]);
    }
}
