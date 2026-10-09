<?php

namespace Tests\Integrations;

use App\Providers\HealthCheckProvider;
use Tests\TestCase;

class HealthCheckRedisOptionalTest extends TestCase
{
    public function testRedisIsCheckedWhenTheQueueUsesIt(): void
    {
        config(['queue.default' => 'redis', 'cache.default' => 'file']);

        $this->assertTrue(HealthCheckProvider::usesRedis());
    }

    public function testRedisIsCheckedWhenTheCacheUsesIt(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'redis']);

        $this->assertTrue(HealthCheckProvider::usesRedis());
    }

    public function testRedisIsNotCheckedWhenQueueAndCacheUseTheDatabase(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'database']);

        $this->assertFalse(HealthCheckProvider::usesRedis());
    }
}
