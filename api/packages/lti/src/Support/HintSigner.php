<?php

namespace Ulams\Lti\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;
use Ulams\Lti\Exceptions\LtiRequestException;

/**
 * Short-lived values we hand out and later get back unchanged (login_hint, lti_message_hint,
 * deep-linking `data`): an HS256 JWT keyed with a secret derived from the tenant APP_KEY, so they
 * cannot be forged and are useless on any other tenant.
 */
class HintSigner
{
    public function sign(string $purpose, array $claims, int $ttl): string
    {
        $now = time();

        return JWT::encode($claims + ['pur' => $purpose, 'iat' => $now, 'exp' => $now + $ttl, 'jti' => bin2hex(random_bytes(12))], $this->secret(), 'HS256');
    }

    /**
     * @throws LtiRequestException
     */
    public function verify(string $purpose, ?string $token): array
    {
        if ($token === null || $token === '') {
            throw new LtiRequestException('Missing ' . $purpose . '.');
        }
        try {
            $claims = (array) JWT::decode($token, new Key($this->secret(), 'HS256'));
        } catch (Throwable) {
            throw new LtiRequestException('Invalid or expired ' . $purpose . '.');
        }
        if (($claims['pur'] ?? null) !== $purpose) {
            throw new LtiRequestException('Invalid ' . $purpose . '.');
        }

        return json_decode(json_encode($claims), true);
    }

    private function secret(): string
    {
        return hash_hmac('sha256', 'ulams-lti-hints', (string) config('app.key'));
    }
}
