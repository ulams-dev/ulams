<?php

namespace Ulams\LivingCourse\Oauth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Ulams\LivingCourse\Connectors\ConnectorException;
use Ulams\LivingCourse\Connectors\OAuthConnector;
use Ulams\LivingCourse\Connectors\SourceConnector;
use Ulams\LivingCourse\Connectors\Remote\RemoteApi;

/**
 * "Connect your Google or Notion account" for source connectors. The installation owns the OAuth app
 * (client id and secret in the settings); the user signs in once, and the answer becomes a one-time
 * grant held server-side (encrypted, ten minutes, bound to the user) that the connect request redeems:
 * the browser never sees a token. Refresh and access tokens then live encrypted in the connection's secrets.
 */
final class OAuthService
{
    private const TTL = 600;

    /** @param (callable(mixed):mixed)|null $handler Guzzle handler (tests) */
    public function __construct(private readonly mixed $handler = null)
    {
    }

    /** @return array<string,OAuthProvider> */
    public function providers(): array
    {
        return ['google' => OAuthProvider::google(), 'notion' => OAuthProvider::notion()];
    }

    public function provider(string $key): OAuthProvider
    {
        return $this->providers()[$key] ?? throw new ConnectorException('This sign-in is not available.');
    }

    public function redirectUri(OAuthProvider $provider): string
    {
        return url('/api/living-course/oauth/' . $provider->key . '/callback');
    }

    /** The page the vendor's sign-in starts on. */
    public function start(string $providerKey, int $userId): string
    {
        $provider = $this->provider($providerKey);
        if (!$provider->configured()) {
            throw new ConnectorException("{$provider->label} sign-in is not set up on this installation. An admin needs to add the OAuth client id and secret.");
        }
        $state = Crypt::encryptString((string) json_encode(['u' => $userId, 'p' => $provider->key, 'n' => bin2hex(random_bytes(8)), 'e' => now()->timestamp + self::TTL]));
        $query = array_filter([
            'client_id' => $provider->clientId(),
            'redirect_uri' => $this->redirectUri($provider),
            'response_type' => 'code',
            'scope' => $provider->scopes === [] ? null : implode(' ', $provider->scopes),
            'state' => $state,
        ]) + $provider->authorizeParams;

        return $provider->authorizeUrl . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The vendor sent the user back with a code: exchange it and keep the secrets for the connect request.
     *
     * @return string the grant id
     * @throws ConnectorException
     */
    public function complete(string $providerKey, string $code, string $state): string
    {
        $provider = $this->provider($providerKey);
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            throw new ConnectorException('This sign-in link is not valid. Start again.');
        }
        if (!is_array($payload) || ($payload['p'] ?? null) !== $provider->key || (int) ($payload['e'] ?? 0) < now()->timestamp) {
            throw new ConnectorException('This sign-in took too long or belongs to another source. Start again.');
        }
        if ($code === '' || !$provider->configured()) {
            throw new ConnectorException("{$provider->label} did not return a sign-in code.");
        }
        $secret = $this->exchange($provider, $code);
        $id = bin2hex(random_bytes(16));
        Cache::put($this->key($id), Crypt::encryptString((string) json_encode(['u' => (int) $payload['u'], 'p' => $provider->key, 's' => [$provider->secretField => $secret]])), self::TTL);

        return $id;
    }

    /**
     * The secrets behind a grant, for the user it was made for. They stay available until `forget()`
     * so a refused connect can be repeated without signing in again.
     *
     * @return array<string,string>
     * @throws ConnectorException
     */
    public function peek(string $grant, int $userId, string $providerKey): array
    {
        $stored = Cache::get($this->key($grant));
        $data = is_string($stored) ? json_decode((string) Crypt::decryptString($stored), true) : null;
        if (!is_array($data) || (int) ($data['u'] ?? 0) !== $userId || ($data['p'] ?? null) !== $providerKey) {
            throw new ConnectorException('The sign-in expired. Connect your account again.');
        }

        return (array) $data['s'];
    }

    /**
     * Swaps `secrets.grant` for the secrets behind it when the connector signs in through OAuth.
     *
     * @param array<string,mixed> $secrets
     * @return array{0:array<string,mixed>,1:?string} the secrets and the grant to forget once the connection is saved
     * @throws ConnectorException
     */
    public function redeem(array $secrets, SourceConnector $connector, int $userId): array
    {
        $grant = isset($secrets['grant']) && is_string($secrets['grant']) ? $secrets['grant'] : null;
        unset($secrets['grant']);
        if ($grant === null || !$connector instanceof OAuthConnector) {
            return [$secrets, null];
        }

        return [$this->peek($grant, $userId, $connector->oauthProvider()) + $secrets, $grant];
    }

    public function forget(string $grant): void
    {
        Cache::forget($this->key($grant));
    }

    private function key(string $grant): string
    {
        return 'living-course:oauth-grant:' . preg_replace('/[^a-f0-9]/', '', $grant);
    }

    private function exchange(OAuthProvider $provider, string $code): string
    {
        $api = new RemoteApi($this->handler, $provider->label, 1048576);
        $redirect = $this->redirectUri($provider);
        $errors = [400 => "{$provider->label} refused the sign-in code. Start again.", 401 => "{$provider->label} refused the OAuth client of this installation. Ask an admin to check the client id and secret."];
        $answer = $provider->tokenStyle === 'form'
            ? $api->json('POST', $provider->tokenUrl, ['form_params' => ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect, 'client_id' => $provider->clientId(), 'client_secret' => $provider->clientSecret()]], $errors)
            : $api->json('POST', $provider->tokenUrl, ['auth' => [$provider->clientId(), $provider->clientSecret()], 'json' => ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirect]], $errors);
        $token = (string) ($answer[$provider->tokenField] ?? '');
        if ($token === '') {
            throw new ConnectorException("{$provider->label} did not grant long-lived access. Remove this app from your {$provider->label} account permissions and connect again.");
        }

        return $token;
    }

    /** A fresh access token from a Google refresh token. */
    public function refreshGoogle(string $refreshToken): string
    {
        $provider = OAuthProvider::google();
        if (!$provider->configured()) {
            throw new ConnectorException('Google sign-in is not set up on this installation any more. An admin needs to add the OAuth client id and secret.');
        }
        $answer = (new RemoteApi($this->handler, 'Google', 1048576))->json('POST', $provider->tokenUrl, ['form_params' => [
            'grant_type' => 'refresh_token', 'refresh_token' => $refreshToken, 'client_id' => $provider->clientId(), 'client_secret' => $provider->clientSecret(),
        ]], [400 => 'Google no longer accepts the saved sign-in. Connect your Google account again.', 401 => 'Google no longer accepts the saved sign-in. Connect your Google account again.']);
        $token = (string) ($answer['access_token'] ?? '');
        if ($token === '') {
            throw new ConnectorException('Google did not return an access token.');
        }

        return $token;
    }
}
