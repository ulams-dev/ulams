<?php

namespace Ulams\Identity\Tests\Support;

use Firebase\JWT\JWT;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * An OpenID Connect provider in memory: discovery, JWKS, token and userinfo endpoints, with locally generated
 * RSA and EC keys. Plugged into the client as `identity.http_handler`; every request is recorded.
 */
class FakeIdp
{
    public const ISSUER = 'https://idp.example.test/realms/acme';
    public const CLIENT_ID = 'ulams-web';
    public const CLIENT_SECRET = 'correct-horse-battery-staple-9f3a1c';

    /** @var list<array{method:string,url:string,headers:array<string,string>,form:array<string,string>}> */
    public array $requests = [];
    /** @var array<string,mixed> overrides merged into the discovery document; null values remove a key */
    public array $discovery = [];
    public int $jwksFetches = 0;
    public int $discoveryFetches = 0;
    public int $tokenStatus = 200;
    /** @var array<string,mixed>|null body of the token endpoint; set by tests */
    public ?array $tokenBody = null;
    /** @var array<string,mixed> */
    public array $userinfo = [];
    public int $userinfoStatus = 200;
    /** @var list<array<string,mixed>> JWKS served; defaults to the RSA key and the EC key */
    public array $jwks;

    /** @var array<string,\OpenSSLAsymmetricKey> kid => private key */
    private array $privateKeys = [];
    /** @var array<string,string> kid => PEM private key */
    private array $pems = [];

    public function __construct()
    {
        $this->addRsa('rsa1');
        $this->addEc('ec1');
        $this->jwks = [$this->publicJwk('rsa1'), $this->publicJwk('ec1')];
    }

    public function addRsa(string $kid): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->store($kid, $key);
    }

    public function addEc(string $kid): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->store($kid, $key);
    }

    private function store(string $kid, \OpenSSLAsymmetricKey $key): void
    {
        $this->privateKeys[$kid] = $key;
        openssl_pkey_export($key, $pem);
        $this->pems[$kid] = $pem;
    }

    /** @return array<string,mixed> */
    public function publicJwk(string $kid): array
    {
        $details = openssl_pkey_get_details($this->privateKeys[$kid]);
        $b64 = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        if ($details['type'] === OPENSSL_KEYTYPE_RSA) {
            return ['kty' => 'RSA', 'kid' => $kid, 'use' => 'sig', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])];
        }

        return ['kty' => 'EC', 'kid' => $kid, 'use' => 'sig', 'crv' => 'P-256', 'x' => $b64($details['ec']['x']), 'y' => $b64($details['ec']['y'])];
    }

    /** @return array<string,mixed> */
    public function discoveryDocument(): array
    {
        $doc = [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER . '/protocol/openid-connect/auth',
            'token_endpoint' => self::ISSUER . '/protocol/openid-connect/token',
            'jwks_uri' => self::ISSUER . '/protocol/openid-connect/certs',
            'userinfo_endpoint' => self::ISSUER . '/protocol/openid-connect/userinfo',
            'code_challenge_methods_supported' => ['S256', 'plain'],
        ];
        foreach ($this->discovery as $key => $value) {
            if ($value === null) {
                unset($doc[$key]);
            } else {
                $doc[$key] = $value;
            }
        }

        return $doc;
    }

    /**
     * Signs an ID token. Defaults describe a valid one for `$nonce`.
     *
     * @param array<string,mixed> $claims overrides; a null value removes the claim
     */
    public function idToken(string $nonce, array $claims = [], string $alg = 'RS256', string $kid = 'rsa1', ?string $secret = null): string
    {
        $now = time();
        $payload = [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'subject-1',
            'exp' => $now + 300,
            'iat' => $now,
            'nonce' => $nonce,
            'email' => 'ada@example.com',
            'email_verified' => true,
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
        ];
        foreach ($claims as $name => $value) {
            if ($value === null) {
                unset($payload[$name]);
            } else {
                $payload[$name] = $value;
            }
        }

        return JWT::encode($payload, $secret ?? $this->pems[$kid], $alg, $kid);
    }

    /** A token with the header and claims given as they are and an arbitrary signature, for `alg: none` cases. */
    public static function unsigned(array $header, array $payload): string
    {
        $b64 = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return $b64(json_encode($header)) . '.' . $b64(json_encode($payload)) . '.';
    }

    public function privatePem(string $kid): string
    {
        return $this->pems[$kid];
    }

    public function publicPem(string $kid): string
    {
        return openssl_pkey_get_details($this->privateKeys[$kid])['key'];
    }

    public function handler(): callable
    {
        return function (RequestInterface $request, array $options) {
            $url = (string) $request->getUri();
            $form = [];
            if ($request->getMethod() === 'POST') {
                parse_str((string) $request->getBody(), $form);
            }
            $this->requests[] = [
                'method' => $request->getMethod(),
                'url' => $url,
                'headers' => array_map(fn (array $v) => implode(',', $v), $request->getHeaders()),
                'form' => $form,
            ];

            $json = static fn (array $body, int $status = 200) => Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
            $path = parse_url($url, PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/.well-known/openid-configuration') => $this->countDiscovery($json),
                str_ends_with($path, '/openid-connect/certs') => $this->countJwks($json),
                str_ends_with($path, '/openid-connect/token') => $this->tokenBody === null ? $json(['error' => 'invalid_grant'], 400) : $json($this->tokenBody, $this->tokenStatus),
                str_ends_with($path, '/openid-connect/userinfo') => $json($this->userinfo, $this->userinfoStatus),
                default => $json(['error' => 'not_found'], 404),
            };
        };
    }

    private function countDiscovery(callable $json)
    {
        $this->discoveryFetches++;

        return $json($this->discoveryDocument());
    }

    private function countJwks(callable $json)
    {
        $this->jwksFetches++;

        return $json(['keys' => $this->jwks]);
    }

    /** @return list<array<string,mixed>> */
    public function requestsTo(string $suffix): array
    {
        return array_values(array_filter($this->requests, fn (array $r) => str_ends_with(parse_url($r['url'], PHP_URL_PATH), $suffix)));
    }
}
