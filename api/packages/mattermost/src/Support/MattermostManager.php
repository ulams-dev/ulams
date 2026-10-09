<?php

namespace Ulams\Mattermost\Support;

use Gnello\Mattermost\Driver;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use Pimple\Container;

/**
 * Builds logged-in `gnello/php-mattermost-driver` clients from `config('mattermost.servers')`.
 *
 * Replaces the Laravel wrapper `gnello/laravel-mattermost-driver`, which has no Laravel 13
 * release. Same behaviour: one client per server name, created and authenticated on first use.
 */
class MattermostManager
{
    /** @var array<string, Driver> */
    private array $servers = [];

    public function __construct(private readonly Config $config)
    {
    }

    public function server(?string $name = null): Driver
    {
        $name ??= $this->getDefaultServer();

        return $this->servers[$name] ??= $this->makeConnection($name);
    }

    public function getDefaultServer(): string
    {
        return (string) $this->config->get('mattermost.default', 'default');
    }

    private function makeConnection(string $name): Driver
    {
        $config = $this->config->get('mattermost.servers.' . $name);

        if (!is_array($config)) {
            throw new InvalidArgumentException("Mattermost server [$name] not configured.");
        }

        $driver = ['url' => $config['host']];
        if (strtoupper((string) ($config['auth'] ?? '')) === 'BEARER') {
            $driver['token'] = $config['token'];
        } else {
            $driver['login_id'] = $config['login'];
            $driver['password'] = $config['password'];
        }
        if (isset($config['scheme'])) {
            $driver['scheme'] = $config['scheme'];
        }

        $client = new Driver(new Container([
            'driver' => $driver,
            'guzzle' => $config['guzzle'] ?? [],
        ]));
        $client->authenticate();

        return $client;
    }

    /**
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->server()->$method(...$parameters);
    }
}
