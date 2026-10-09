<?php

namespace Ulams\LiaScript\Services;

/**
 * Token of the content-origin LiaScript player: it may only report progress of one learner in one
 * topic. HMAC-SHA256 with a key derived from the tenant APP_KEY (rejected by other tenants).
 */
final class ProgressToken
{
    public static function issue(int $userId, int $topicId, int $ttl): string
    {
        $payload = self::encode((string) json_encode(['u' => $userId, 't' => $topicId, 'exp' => time() + $ttl]));

        return $payload . '.' . self::encode(hash_hmac('sha256', $payload, self::key(), true));
    }

    /**
     * @return int|null user id when the token is valid for this topic
     */
    public static function verify(?string $token, int $topicId): ?int
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $signature] = explode('.', $token);
        if (!hash_equals(self::encode(hash_hmac('sha256', $payload, self::key(), true)), $signature)) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        if (!is_array($data) || ($data['t'] ?? null) !== $topicId || (int) ($data['exp'] ?? 0) < time()) {
            return null;
        }

        return (int) $data['u'];
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'ulams-liascript-progress-token', (string) config('app.key'), true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
