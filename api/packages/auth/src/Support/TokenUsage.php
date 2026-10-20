<?php

namespace Ulams\Auth\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Models\ApiTokenUsage;

/**
 * Daily request counters of scoped tokens, and the usage summary the API serves. Written after the
 * response (`RecordAgentAudit::terminate`), so counting never slows or fails a request.
 */
final class TokenUsage
{
    public const MAX_DAYS = 90;

    public static function record(string $tokenId, int $status, bool $rateLimited): void
    {
        $day = now()->toDateString();
        $increments = [
            'requests' => DB::raw('requests + 1'),
            'errors' => DB::raw('errors + ' . ($status >= 400 && !$rateLimited ? 1 : 0)),
            'rate_limited' => DB::raw('rate_limited + ' . ($rateLimited ? 1 : 0)),
            'updated_at' => now(),
        ];
        $updated = ApiTokenUsage::query()->where('token_id', $tokenId)->whereDate('day', $day)->update($increments);
        if ($updated > 0) {
            return;
        }
        try {
            ApiTokenUsage::query()->create([
                'token_id' => $tokenId, 'day' => $day, 'requests' => 1,
                'errors' => $status >= 400 && !$rateLimited ? 1 : 0, 'rate_limited' => $rateLimited ? 1 : 0,
            ]);
        } catch (QueryException) {
            // another request created today's row first
            ApiTokenUsage::query()->where('token_id', $tokenId)->whereDate('day', $day)->update($increments);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function summary(ApiTokenMeta $meta, int $days): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $from = now()->subDays($days - 1)->toDateString();
        $rows = ApiTokenUsage::query()->where('token_id', $meta->token_id)->whereDate('day', '>=', $from)->orderBy('day')->get();

        $daily = $rows->map(fn (ApiTokenUsage $r) => [
            'date' => $r->day->toDateString(), 'requests' => $r->requests, 'errors' => $r->errors, 'rate_limited' => $r->rate_limited,
        ])->values()->all();

        $limit = $meta->rate_limit_per_minute;
        $window = null;
        if ($limit !== null) {
            $key = EnforceKey::for($meta->token_id);
            $used = min($limit, RateLimiter::attempts($key));
            $window = ['limit' => $limit, 'used' => $used, 'remaining' => max(0, $limit - $used), 'resets_in_seconds' => $used > 0 ? RateLimiter::availableIn($key) : 0];
        }

        return [
            'token_id' => $meta->token_id,
            'days' => $days,
            'from' => $from,
            'to' => now()->toDateString(),
            'rate_limit_per_minute' => $limit,
            'window' => $window,
            'last_used_at' => $meta->last_used_at?->toIso8601String(),
            'totals' => [
                'requests' => (int) $rows->sum('requests'),
                'errors' => (int) $rows->sum('errors'),
                'rate_limited' => (int) $rows->sum('rate_limited'),
            ],
            'daily' => $daily,
        ];
    }
}
