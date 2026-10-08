<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ulams\Tenancy\Tests\TestCase;

class RejectUnknownHostTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('api/tenancy-probe', fn () => response()->json(['ok' => true]));
        config([
            'ulams_tenancy.platform_hosts' => ['api.localhost', 'localhost', 'caddy'],
            'domain.domains' => ['coffee.localhost' => 'coffee.localhost', 'stale.localhost' => 'stale.localhost'],
        ]);
        $this->envFile = $this->app->environmentPath() . '/.env.coffee.localhost';
        file_put_contents($this->envFile, "APP_NAME=Coffee\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
        parent::tearDown();
    }

    public function testUnknownHostGets404(): void
    {
        $this->getJson('http://nope.localhost/api/tenancy-probe')
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function testPlatformHostsPass(): void
    {
        $this->getJson('http://api.localhost/api/tenancy-probe')->assertOk();
        $this->getJson('http://localhost/api/tenancy-probe')->assertOk();
        $this->getJson('http://caddy/api/tenancy-probe')->assertOk();
    }

    public function testRegisteredTenantHostPasses(): void
    {
        $this->getJson('http://coffee.localhost/api/tenancy-probe')->assertOk();
        $this->getJson('http://coffee.localhost:8080/api/tenancy-probe')->assertOk();
    }

    public function testRegisteredHostWithoutEnvFileGets404(): void
    {
        // laravel-multidomain would serve it with the platform .env
        $this->getJson('http://stale.localhost/api/tenancy-probe')->assertNotFound();
    }

    public function testSubdomainOfATenantGets404(): void
    {
        $this->getJson('http://x.coffee.localhost/api/tenancy-probe')->assertNotFound();
    }

    public function testForwardedHostDecides(): void
    {
        $this->getJson('http://caddy/api/tenancy-probe', ['X-Forwarded-Host' => 'nope.localhost'])->assertNotFound();
        $this->getJson('http://caddy/api/tenancy-probe', ['X-Forwarded-Host' => 'coffee.localhost'])->assertOk();
    }

    public function testCanBeDisabled(): void
    {
        config(['ulams_tenancy.enforce_known_hosts' => false]);

        $this->getJson('http://nope.localhost/api/tenancy-probe')->assertOk();
    }
}
