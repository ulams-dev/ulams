<?php

namespace Ulams\Tenancy\Tests\Unit;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Ulams\Tenancy\Tests\TestCase;

/**
 * api/init-keys.sh: APP_KEY encrypts the tenant secrets, so a missing Passport key must never
 * lead to a new APP_KEY. A stand-in `php` records the artisan commands it is asked to run.
 */
class InitKeysScriptTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/ulams-init-keys-' . uniqid();
        mkdir($this->base . '/bin', 0777, true);
        mkdir($this->base . '/storage', 0777, true);
        file_put_contents($this->base . '/bin/php', "#!/bin/bash\necho \"\$@\" >> \"" . $this->base . "/commands.log\"\n");
        chmod($this->base . '/bin/php', 0755);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function run_(array $env = [], array $args = []): array
    {
        $script = realpath(__DIR__ . '/../../../../init-keys.sh');
        $process = new Process(['bash', $script, ...$args], $this->base, array_merge([
            'PATH' => $this->base . '/bin:' . getenv('PATH'),
            'APP_KEY' => false,
        ], $env));
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $log = is_file($this->base . '/commands.log') ? trim((string) file_get_contents($this->base . '/commands.log')) : '';

        return $log === '' ? [] : explode("\n", $log);
    }

    public function testExistingAppKeyIsNeverRegeneratedWhenPassportKeysAreMissing(): void
    {
        file_put_contents($this->base . '/.env', "APP_NAME=Ulams\nAPP_KEY=base64:abc123\n");

        $commands = $this->run_();

        $this->assertSame(
            ['artisan passport:keys --force --no-interaction', 'artisan passport:client --personal --no-interaction'],
            $commands
        );
    }

    public function testAppKeyFromTheEnvironmentCountsAsSet(): void
    {
        file_put_contents($this->base . '/.env', "APP_KEY=\n");

        $commands = $this->run_(['APP_KEY' => 'base64:fromenv']);

        $this->assertNotContains('artisan key:generate --force --no-interaction', $commands);
    }

    public function testEmptyAppKeyIsGeneratedOnce(): void
    {
        file_put_contents($this->base . '/.env', "APP_KEY=\n");
        file_put_contents($this->base . '/storage/oauth-private.key', 'x');

        $this->assertSame(['artisan key:generate --force --no-interaction'], $this->run_());
    }

    public function testNothingRunsWhenBothKeysExist(): void
    {
        file_put_contents($this->base . '/.env', "APP_KEY=base64:abc123\n");
        file_put_contents($this->base . '/storage/oauth-private.key', 'x');

        $this->assertSame([], $this->run_());
    }

    public function testDomainJudgesItsOwnEnvFileNotTheProcessAppKey(): void
    {
        file_put_contents($this->base . '/.env.acme.localhost', "APP_KEY=\n");
        mkdir($this->base . '/storage/acme', 0777, true);
        file_put_contents($this->base . '/storage/acme/oauth-private.key', 'x');

        $commands = $this->run_(
            ['APP_KEY' => 'base64:platform', 'ENV_FILE' => '.env.acme.localhost', 'PASSPORT_PRIVATE_KEY_FILE' => 'storage/acme/oauth-private.key'],
            ['--domain=acme.localhost']
        );

        $this->assertSame(['artisan key:generate --force --no-interaction --domain=acme.localhost'], $commands);
    }
}
