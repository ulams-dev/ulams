<?php

namespace Ulams\Tenancy\Tests\Unit;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Ulams\Tenancy\Services\ProcessTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

class ProcessTenantCommandRunnerTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/ulams-runner-' . uniqid();
        mkdir($this->base);
        file_put_contents($this->base . '/.env', "DB_DATABASE=platform\nREDIS_PREFIX=platform_\n");
        file_put_contents($this->base . '/.env.acme.localhost', "DB_DATABASE=ulams_acme\nTENANT_ONLY=1\n");
        // stands in for artisan: prints its arguments and the inherited environment
        file_put_contents($this->base . '/artisan', <<<'PHP'
<?php
echo json_encode([
    'argv' => array_slice($argv, 1),
    'DB_DATABASE' => getenv('DB_DATABASE'),
    'REDIS_PREFIX' => getenv('REDIS_PREFIX'),
    'PATH' => getenv('PATH') !== false,
]);
exit(in_array('fail', $argv, true) ? 3 : 0);
PHP);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        putenv('DB_DATABASE');
        putenv('REDIS_PREFIX');
        parent::tearDown();
    }

    public function testRunsArtisanForTheTenantWithoutInheritingPlatformEnv(): void
    {
        // what the platform process looks like after Dotenv loaded its .env
        putenv('DB_DATABASE=platform');
        putenv('REDIS_PREFIX=platform_');

        $output = (new ProcessTenantCommandRunner($this->base, PHP_BINARY))->run('acme.localhost', ['migrate', '--force']);
        $result = json_decode($output, true);

        $this->assertSame(['migrate', '--force', '--no-interaction', '--domain=acme.localhost'], $result['argv']);
        $this->assertFalse($result['DB_DATABASE']);
        $this->assertFalse($result['REDIS_PREFIX']);
        $this->assertTrue($result['PATH']);
    }

    public function testScrubsKeysOfBothEnvFiles(): void
    {
        $env = (new ProcessTenantCommandRunner($this->base))->scrubbedEnvironment('acme.localhost');

        $this->assertFalse($env['DB_DATABASE']);
        $this->assertFalse($env['REDIS_PREFIX']);
        $this->assertFalse($env['TENANT_ONLY']);
        $this->assertArrayNotHasKey('PATH', $env);
    }

    public function testFailureThrowsWithTheOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exit 3');

        (new ProcessTenantCommandRunner($this->base, PHP_BINARY))->run('acme.localhost', ['fail']);
    }
}
