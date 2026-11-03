<?php

namespace Ulams\Identity\Tests\Support;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Ulams\Auth\Services\LoginHandoffService;

/** Drives the browser side of an OpenID Connect sign-in against the fake provider. */
trait RunsOidcLogin
{
    protected string $verifier = '';

    protected const RETURN_TO = 'https://app.example.test/auth/sso/callback';

    /** The URL on a given tenant host (the test client takes the host from the URL). */
    protected function url(string $path, ?string $host = null): string
    {
        return $host ? 'http://' . $host . '/' . $path : $path;
    }

    protected function newVerifier(): string
    {
        return $this->verifier = Str::random(64);
    }

    protected function challenge(): string
    {
        return LoginHandoffService::challengeFor($this->verifier ?: $this->newVerifier());
    }

    protected function start(string $slug = 'acme', array $query = [], ?string $host = null): TestResponse
    {
        $query = $query + ['challenge' => $this->challenge(), 'return_to' => self::RETURN_TO];
        $query = array_filter($query, fn ($v) => $v !== null);

        return $this->get($this->url('api/identity/sso/' . $slug . '/start?' . http_build_query($query), $host));
    }

    /** @return array<string,string> */
    protected function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        return $params;
    }

    /**
     * Starts a login and answers the callback as the provider would.
     *
     * @param array<string,mixed> $claims overrides of the ID token claims (null removes one)
     * @param array{alg?:string,kid?:string,secret?:string,id_token?:string,state?:string,code?:?string,error?:string,host?:string,slug?:string,start_host?:string} $options
     * @return array{response:TestResponse,auth:array<string,string>,state:string}
     */
    protected function signIn(array $claims = [], array $options = []): array
    {
        $started = $this->start($options['slug'] ?? 'acme', [], $options['start_host'] ?? null);
        $started->assertRedirect();
        $auth = $this->queryOf($started->headers->get('Location'));
        $state = $options['state'] ?? $auth['state'];

        $this->idp->tokenBody = [
            'token_type' => 'Bearer',
            'access_token' => 'access-token-1',
            'id_token' => $options['id_token'] ?? $this->idp->idToken($auth['nonce'], $claims, $options['alg'] ?? 'RS256', $options['kid'] ?? 'rsa1', $options['secret'] ?? null),
        ];

        return ['response' => $this->answerCallback($options['slug'] ?? 'acme', $state, $options), 'auth' => $auth, 'state' => $state];
    }

    protected function answerCallback(string $slug, ?string $state, array $options = []): TestResponse
    {
        $query = array_filter(['state' => $state, 'code' => array_key_exists('code', $options) ? $options['code'] : 'auth-code', 'error' => $options['error'] ?? null], fn ($v) => $v !== null);
        $host = $options['host'] ?? null;

        return $this->get($this->url('api/identity/oidc/' . $slug . '/callback?' . http_build_query($query), $host));
    }

    /** The query of the redirect back to the front end. */
    protected function returned(TestResponse $response): array
    {
        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::RETURN_TO . '?', $location);

        return $this->queryOf($location);
    }

    protected function exchange(string $code, ?string $verifier = null, ?string $host = null): TestResponse
    {
        return $this->postJson($this->url('api/identity/sso/exchange', $host), ['code' => $code, 'verifier' => $verifier ?? $this->verifier]);
    }
}
