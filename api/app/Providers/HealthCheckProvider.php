<?php

namespace App\Providers;

use App\Health\HeartbeatCheck;
use App\Health\ObjectStorageCheck;
use App\Health\ServiceReachableCheck;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Facades\Health;
use Ulams\Core\Observability\Heartbeat;

class HealthCheckProvider extends ServiceProvider
{
    public static function usesRedis(): bool
    {
        return config('queue.default') === 'redis' || config('cache.default') === 'redis';
    }

    /** Readiness checks behind /api/health (liveness is /up and checks nothing). */
    public static function checks(): array
    {
        $checks = [DatabaseCheck::new()];
        // Redis is optional when queues and cache use the database (shared hosting, ADR 0091)
        if (self::usesRedis()) {
            $checks[] = RedisCheck::new();
        }
        $checks[] = HeartbeatCheck::for(Heartbeat::SCHEDULER);
        $checks[] = HeartbeatCheck::for(Heartbeat::QUEUE);
        $checks[] = ObjectStorageCheck::new()->name('object-storage')->label('Object storage');
        // only where Horizon supervises the workers (HEALTH_CHECK_HORIZON=true)
        if (config('health.check_horizon', false)) {
            $checks[] = HorizonCheck::new();
        }
        $checks = array_merge($checks, array_filter([
            ServiceReachableCheck::for('pdf', config('ulams_templates_pdf.pdf.service_url')),
            ServiceReachableCheck::for('mjml', self::origin(config('ulams_templates_email.mjml.api_url')), '/healthz'),
        ]));

        return $checks;
    }

    /** scheme://host:port of a service URL that may carry a path */
    private static function origin(mixed $url): ?string
    {
        $parts = is_string($url) ? parse_url($url) : false;
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public function register()
    {
        Health::checks(self::checks());
    }
}
