<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\Cache;
use Ulams\LivingCourse\Models\Connection;

/** At most `living_course.poll.checks_per_connection_per_day` automatic checks (poll, webhook) per connection and day. */
final class CheckLimiter
{
    public function allow(Connection $connection): bool
    {
        $key = 'living_course:checks:' . $connection->id . ':' . now()->format('Ymd');
        Cache::add($key, 0, now()->addDay());
        $count = (int) Cache::increment($key);

        return $count <= (int) config('living_course.poll.checks_per_connection_per_day', 48);
    }
}
