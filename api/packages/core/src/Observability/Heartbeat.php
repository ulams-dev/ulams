<?php

namespace Ulams\Core\Observability;

use Illuminate\Support\Facades\Cache;

/**
 * A timestamp in the (per-tenant prefixed) cache that a scheduled command and a queued job write every
 * minute. Their age tells whether the scheduler and a queue worker of this tenant are alive, which
 * neither the web process nor the database can say.
 */
final class Heartbeat
{
    public const SCHEDULER = 'scheduler';
    public const QUEUE = 'queue';

    /** Beats older than this count as stale (the scheduler beats every minute). */
    public const STALE_AFTER_SECONDS = 300;

    public static function beat(string $name): void
    {
        Cache::put(self::key($name), time(), 86400);
    }

    /** Seconds since the last beat, or null when there was none (or the cache is unreachable). */
    public static function age(string $name): ?int
    {
        try {
            $at = Cache::get(self::key($name));
        } catch (\Throwable) {
            return null;
        }

        return is_numeric($at) ? max(0, time() - (int) $at) : null;
    }

    private static function key(string $name): string
    {
        return 'ulams:heartbeat:' . $name;
    }
}
