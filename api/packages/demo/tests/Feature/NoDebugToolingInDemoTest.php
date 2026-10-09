<?php

namespace Ulams\Demo\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The demo and production images install vendor/ without dev packages, so Ignition's
 * `/_ignition/*` routes (execute-solution, update-config) do not exist there. This guards the three
 * places that make that true.
 */
class NoDebugToolingInDemoTest extends TestCase
{
    private string $apiRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiRoot = dirname(__DIR__, 4);
    }

    public function testIgnitionIsADevOnlyDependency(): void
    {
        $composer = json_decode(file_get_contents($this->apiRoot . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('spatie/laravel-ignition', $composer['require']);
        $this->assertArrayHasKey('spatie/laravel-ignition', $composer['require-dev']);
    }

    public function testDemoProfileInstallsVendorWithoutDevPackages(): void
    {
        $profile = file_get_contents($this->apiRoot . '/php-profile.sh');

        $this->assertMatchesRegularExpression('/composer install[^\n]*--no-dev/', $profile);
    }

    public function testProductionImageInstallsVendorWithoutDevPackages(): void
    {
        $dockerfile = file_get_contents($this->apiRoot . '/Dockerfile');

        $this->assertMatchesRegularExpression('/composer install[^\n]*--no-dev/', $dockerfile);
    }

    public function testDemoComposeTurnsDebugOff(): void
    {
        $compose = file_get_contents($this->apiRoot . '/docker-compose.demo.yml');

        $this->assertStringContainsString('APP_DEBUG=false', $compose);
    }
}
