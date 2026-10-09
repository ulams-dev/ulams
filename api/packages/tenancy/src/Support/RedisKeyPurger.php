<?php

namespace Ulams\Tenancy\Support;

use Predis\Client;

/**
 * Deletes every Redis key of a tenant. Uses a client without the platform's own key prefix,
 * so the tenant prefix is matched as-is. Works with both clients Laravel supports: phpredis
 * (the default) and Predis.
 */
class RedisKeyPurger
{
    public function purge(string $prefix): int
    {
        if ($prefix === '') {
            return 0;
        }

        $connection = (array) config('database.redis.default', []);
        $databases = array_unique([(int) ($connection['database'] ?? 0), (int) config('database.redis.cache.database', 1)]);

        return match (config('database.redis.client')) {
            'phpredis' => class_exists(\Redis::class) ? $this->purgeWithPhpRedis($connection, $databases, $prefix) : 0,
            'predis' => $this->purgeWithPredis($connection, $databases, $prefix),
            default => 0,
        };
    }

    /** @param list<int> $databases */
    private function purgeWithPhpRedis(array $connection, array $databases, string $prefix): int
    {
        $redis = new \Redis();
        $redis->connect((string) ($connection['host'] ?? '127.0.0.1'), (int) ($connection['port'] ?? 6379));
        if (($connection['password'] ?? null) !== null && $connection['password'] !== '') {
            $redis->auth($connection['password']);
        }
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);

        $deleted = 0;
        foreach ($databases as $database) {
            $redis->select($database);
            $cursor = null;
            while (($keys = $redis->scan($cursor, $prefix . '*', 500)) !== false) {
                if ($keys) {
                    $deleted += (int) $redis->del($keys);
                }
                if ($cursor === 0) {
                    break;
                }
            }
        }
        $redis->close();

        return $deleted;
    }

    /** @param list<int> $databases */
    private function purgeWithPredis(array $connection, array $databases, string $prefix): int
    {
        $client = new Client(array_filter([
            'scheme' => 'tcp',
            'host' => $connection['host'] ?? '127.0.0.1',
            'port' => $connection['port'] ?? 6379,
            'password' => $connection['password'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $deleted = 0;
        foreach ($databases as $database) {
            $client->select($database);
            $cursor = '0';
            do {
                [$cursor, $keys] = $client->scan($cursor, ['MATCH' => $prefix . '*', 'COUNT' => 500]);
                if ($keys) {
                    $deleted += $client->del($keys);
                }
            } while ((string) $cursor !== '0');
        }

        return $deleted;
    }
}
