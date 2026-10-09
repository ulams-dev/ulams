<?php

namespace Ulams\Tenancy\Tests\Integration;

use Illuminate\Support\Facades\Redis;
use Ulams\Tenancy\Support\RedisKeyPurger;
use Ulams\Tenancy\Tests\TestCase;

/**
 * Against the Redis of the test environment (skipped when none is reachable): only keys with
 * the tenant prefix go, with either client.
 */
class RedisKeyPurgerTest extends TestCase
{
    public function testPurgesOnlyThePrefixWithBothClients(): void
    {
        foreach (['phpredis', 'predis'] as $client) {
            if ($client === 'phpredis' && !class_exists(\Redis::class)) {
                continue;
            }
            config(['database.redis.client' => $client, 'database.redis.options.prefix' => '']);
            app('redis')->purge('default');
            try {
                $redis = Redis::connection('default');
                $redis->set('ulams_purgetest_a', '1');
                $redis->set('ulams_purgetest_b', '1');
                $redis->set('ulams_keepme_purgetest', '1');
            } catch (\Throwable $exception) {
                $this->markTestSkipped('No Redis: ' . $exception->getMessage());
            }

            $this->assertSame(2, (new RedisKeyPurger())->purge('ulams_purgetest_'), $client);
            $this->assertSame(0, (int) $redis->exists('ulams_purgetest_a'), $client);
            $this->assertSame(1, (int) $redis->exists('ulams_keepme_purgetest'), $client);
            $redis->del('ulams_keepme_purgetest');
        }
    }
}
