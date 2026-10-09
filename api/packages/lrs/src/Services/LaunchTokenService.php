<?php

namespace Ulams\Lrs\Services;

use Illuminate\Support\Str;
use Ulams\Lrs\Extensions\SessionToken;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\LaunchToken;

/**
 * cmi5 launch tokens (ADR 0046). `issue()` creates the one-time token that goes into the launch
 * URL; `exchange()` turns it into the LRS-only session token. The learner's Passport token never
 * reaches the AU.
 */
class LaunchTokenService
{
    /** How long a launch URL may wait for its first fetch. */
    public const LAUNCH_WINDOW_MINUTES = 10;

    /**
     * @return string the one-time token (only its hash is stored)
     */
    public function issue(int $userId, string $registration, ?int $auId, Access $access): string
    {
        // expired rows are of no use to anyone
        LaunchToken::query()->where('expires_at', '<', now()->subDay())->delete();

        $token = Str::random(64);

        LaunchToken::query()->create([
            'token_hash' => LaunchToken::hash($token),
            'user_id' => $userId,
            'registration' => strtolower($registration),
            'au_id' => $auId,
            'access_uuid' => strtolower((string) $access->uuid),
            'expires_at' => now()->addMinutes(self::LAUNCH_WINDOW_MINUTES),
        ]);

        return $token;
    }

    /**
     * The first call opens the session: it fixes its end and returns the LRS token. Later calls
     * within the session return the same token (cmi5 allows an AU to fetch more than once). A
     * launch token that was never used expires after the launch window; a used one with its
     * session.
     *
     * @return string|null null for an unknown, expired or malformed token
     */
    public function exchange(string $oneTimeToken): ?string
    {
        if ($oneTimeToken === '' || strlen($oneTimeToken) > 128) {
            return null;
        }

        $launch = LaunchToken::query()->where('token_hash', LaunchToken::hash($oneTimeToken))->first();

        if (!$launch || $launch->expires_at->isPast()) {
            return null;
        }

        if ($launch->used_at === null) {
            $launch->forceFill([
                'used_at' => now(),
                'expires_at' => now()->addMinutes((int) config('ulams_lrs.session_minutes', 120)),
            ])->save();
        }

        return SessionToken::issue(
            $launch->getKey(),
            (int) $launch->user_id,
            (string) $launch->registration,
            $launch->au_id !== null ? (int) $launch->au_id : null,
            (string) $launch->access_uuid,
            $launch->expires_at->getTimestamp(),
        );
    }
}
