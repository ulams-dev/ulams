<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The change a connection is about to check (plan 9.1): a webhook or a CI job says "this commit, from PR #123" before the check
 * runs, and the check (debounced, unique per connection) takes the latest note when it creates the revision. A burst of pushes
 * therefore ends as one revision that names the last change. Notes expire after a day.
 */
final class ChangeNote
{
    private const TTL_HOURS = 24;

    /** @param array<string,mixed> $change */
    public static function put(string $connectionId, array $change): void
    {
        $current = Cache::get(self::key($connectionId));
        // a CI note only names the commit: it must not wipe the richer note a webhook left for the same commit
        if (is_array($current) && ($change['source'] ?? null) === 'ci' && ($current['commit'] ?? null) === ($change['commit'] ?? null)) {
            return;
        }
        Cache::put(self::key($connectionId), $change, now()->addHours(self::TTL_HOURS));
    }

    /** @return array<string,mixed>|null */
    public static function take(string $connectionId): ?array
    {
        $change = Cache::pull(self::key($connectionId));

        return is_array($change) ? $change : null;
    }

    private static function key(string $connectionId): string
    {
        return "living_course:change:{$connectionId}";
    }
}
