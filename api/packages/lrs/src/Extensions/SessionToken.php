<?php

namespace Ulams\Lrs\Extensions;

/**
 * The LRS-only session token that `POST /api/cmi5/fetch` returns to an AU (ADR 0046). It is not a
 * Passport token: only the LRS guard accepts it, and it is bound to one learner, one registration,
 * one AU and one xAPI access.
 *
 * Format: `ulrs1.` base64url(json {i, u, r, a, x, exp}) "." base64url(HMAC-SHA256). The key is
 * derived from the tenant's APP_KEY, so a token of one tenant is rejected by every other tenant.
 */
final class SessionToken
{
    public const PREFIX = 'ulrs1.';

    /**
     * @param int $launchId id of the lrs_launch_tokens row
     */
    public static function issue(int $launchId, int $userId, string $registration, ?int $auId, string $accessUuid, int $expiresAt): string
    {
        $payload = self::encode((string) json_encode([
            'i' => $launchId,
            'u' => $userId,
            'r' => strtolower($registration),
            'a' => $auId,
            'x' => strtolower($accessUuid),
            'exp' => $expiresAt,
        ]));

        return self::PREFIX . $payload . '.' . self::encode(hash_hmac('sha256', $payload, self::key(), true));
    }

    public static function looksLikeOne(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * @return array{i: int, u: int, r: string, a: int|null, x: string, exp: int}|null null when the
     *         signature is wrong or the token is malformed or expired
     */
    public static function verify(string $token): ?array
    {
        if (!self::looksLikeOne($token) || substr_count($token, '.') !== 2) {
            return null;
        }

        [, $payload, $signature] = explode('.', $token);
        $expected = self::encode(hash_hmac('sha256', $payload, self::key(), true));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode((string) self::decode($payload), true);

        if (!is_array($data) || !isset($data['i'], $data['u'], $data['r'], $data['x'], $data['exp']) || (int) $data['exp'] < time()) {
            return null;
        }

        return [
            'i' => (int) $data['i'],
            'u' => (int) $data['u'],
            'r' => (string) $data['r'],
            'a' => isset($data['a']) ? (int) $data['a'] : null,
            'x' => (string) $data['x'],
            'exp' => (int) $data['exp'],
        ];
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'ulams-lrs-session-token', (string) config('app.key'), true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
