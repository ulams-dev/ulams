<?php

namespace Ulams\Tenancy\Support;

use Predis\Client;

/**
 * Deletes every Redis key of a tenant. Uses a client without the platform's own key prefix,
 * so the tenant prefix is matched as-is.
 */
class RedisKeyPurger
{
    public function purge(string $prefix): int
    {
        if ($prefix === '' || config('database.redis.client') !== 'predis') {
            return 0;
        }

        $connection = (array) config('database.redis.default', []);
        $client = new Client(array_filter([
            'scheme' => 'tcp',
            'host' => $connection['host'] ?? '127.0.0.1',
            'port' => $connection['port'] ?? 6379,
            'password' => $connection['password'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $deleted = 0;
        foreach (array_unique([(int) ($connection['database'] ?? 0), (int) config('database.redis.cache.database', 1)]) as $database) {
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
