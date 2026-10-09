<?php

namespace Ulams\Ai\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Drivers\FakeDriver;
use Ulams\Ai\UlamsAiServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsAiServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ai.driver', 'fake');
        $app['config']->set('ai.fake.mode', 'cassette');
        $app['config']->set('ai.fake.cassettes', sys_get_temp_dir() . '/ulams-ai-cassettes-' . getmypid());
        $app['config']->set('ai.limits.tenant_monthly_usd', 1000000);
    }

    protected function fake(): FakeDriver
    {
        /** @var FakeDriver $driver */
        $driver = $this->app->make(LlmDriver::class);

        return $driver;
    }
}
