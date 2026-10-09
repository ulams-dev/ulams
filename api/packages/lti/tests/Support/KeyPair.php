<?php

namespace Ulams\Lti\Tests\Support;

use Firebase\JWT\JWT;

/**
 * RSA key pair of a fake tool or platform in tests.
 */
final class KeyPair
{
    public readonly string $private;
    public readonly string $public;
    public readonly string $kid;
    public readonly array $jwk;

    public function __construct(?string $kid = null)
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);

        $this->private = $private;
        $this->public = $details['key'];
        $this->kid = $kid ?? bin2hex(random_bytes(8));
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $this->jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $this->kid,
            'n' => $b64($details['rsa']['n']),
            'e' => $b64($details['rsa']['e']),
        ];
    }

    public function sign(array $claims): string
    {
        return JWT::encode($claims, $this->private, 'RS256', $this->kid);
    }

    public function jwks(): array
    {
        return ['keys' => [$this->jwk]];
    }
}
