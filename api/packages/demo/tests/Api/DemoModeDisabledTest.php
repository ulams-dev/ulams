<?php

namespace Ulams\Demo\Tests\Api;

use Ulams\Demo\Tests\TestCase;

/**
 * DEMO_MODE is off (the default, and the platform setting): nothing of the package is exposed.
 */
class DemoModeDisabledTest extends TestCase
{
    protected function demoModeEnabled(): bool
    {
        return false;
    }

    public function testDemoRoutesDoNotExist(): void
    {
        $this->seedDemoUsers();

        $this->getJson('/api/demo')->assertNotFound();
        $this->postJson('/api/demo/login', ['role' => 'admin'])->assertNotFound();
        $this->postJson('/api/demo/login', ['role' => 'student'])->assertNotFound();
    }

    public function testPublicConfigHasNoDemoKey(): void
    {
        $this->getJson('/api/config')->assertOk()->assertJsonMissingPath('data.ulams_demo');
    }

    public function testResetRefusesToRun(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);

        $this->artisan('ulams:demo:reset', ['--force' => true])
            ->expectsOutputToContain('Demo mode is off')
            ->assertExitCode(1);
    }
}
