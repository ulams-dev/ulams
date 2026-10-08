<?php

namespace Ulams\Tenancy\Tests\Mocks;

use RuntimeException;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;

/**
 * Records tenant artisan calls instead of starting processes. `passport:keys` writes a fake
 * key pair into the storage directory, like the real command.
 */
class FakeTenantCommandRunner implements TenantCommandRunnerContract
{
    /** @var list<array{host: string, arguments: list<string>}> */
    public array $calls = [];

    /** @var array<string, bool> command name => fail */
    public array $failOn = [];

    public function __construct(private string $storagePath)
    {
    }

    public function run(string $host, array $arguments): string
    {
        $this->calls[] = ['host' => $host, 'arguments' => $arguments];
        $command = $arguments[0];

        if (!empty($this->failOn[$command])) {
            throw new RuntimeException("{$command} exploded");
        }
        if ($command === 'passport:keys') {
            @mkdir($this->storagePath, 0777, true);
            file_put_contents($this->storagePath . '/oauth-private.key', "PRIVATE-{$host}");
            file_put_contents($this->storagePath . '/oauth-public.key', "PUBLIC-{$host}");
        }

        return '';
    }

    public function commands(): array
    {
        return array_map(fn ($call) => $call['arguments'][0], $this->calls);
    }
}
