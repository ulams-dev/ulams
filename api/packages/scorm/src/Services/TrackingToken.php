<?php

namespace Ulams\Scorm\Services;

/**
 * Short-lived token for the content-origin player: it may read one SCO's launch data and write
 * one learner's tracking for that SCO, nothing else. It replaces the learner's Passport token,
 * which must never reach the content origin (third-party package code runs there).
 *
 * Format: base64url(json {u, s, exp}) "." base64url(HMAC-SHA256). The key is derived from the
 * tenant's APP_KEY, so a token of one tenant is rejected by every other tenant.
 */
final class TrackingToken
{
    public static function issue(int $userId, string $scoUuid, int $ttl): string
    {
        $payload = self::encode((string) json_encode(['u' => $userId, 's' => $scoUuid, 'exp' => time() + $ttl]));

        return $payload . '.' . self::encode(hash_hmac('sha256', $payload, self::key(), true));
    }

    /**
     * @return int|null the user id, or null when the token is invalid, expired or for another SCO
     */
    public static function verify(?string $token, string $scoUuid): ?int
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $signature] = explode('.', $token);
        $expected = self::encode(hash_hmac('sha256', $payload, self::key(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode((string) self::decode($payload), true);
        if (!is_array($data) || !isset($data['u'], $data['s'], $data['exp'])) {
            return null;
        }
        if (!hash_equals((string) $data['s'], $scoUuid) || (int) $data['exp'] < time()) {
            return null;
        }

        return (int) $data['u'];
    }

    public static function expiresAt(string $token): ?int
    {
        $data = json_decode((string) self::decode(explode('.', $token)[0]), true);

        return is_array($data) && isset($data['exp']) ? (int) $data['exp'] : null;
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'ulams-scorm-tracking-token', (string) config('app.key'), true);
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
