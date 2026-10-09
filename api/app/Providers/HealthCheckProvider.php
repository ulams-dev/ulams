<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\RedisCheck;

class HealthCheckProvider extends ServiceProvider
{
    public static function usesRedis(): bool
    {
        return config('queue.default') === 'redis' || config('cache.default') === 'redis';
    }

    public function register()
    {
        $checks = [];
        $checks[] = DatabaseCheck::new();
        // Redis is optional when queues and cache use the database (shared hosting, ADR 0091)
        if (self::usesRedis()) {
            $checks[] = RedisCheck::new();
        }

        Health::checks($checks);

        /**
         * TODO: add more checkchecks
        Health::checks([
            HorizonCheck::new(),
        ]);
         */
    }
}
