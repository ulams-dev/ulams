<?php

namespace Ulams\Tenancy\Services;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * Wraps the gecche/laravel-multidomain `domain:add` / `domain:remove` commands. They only
 * write files, so running them in the platform process is safe.
 */
class MultidomainRegistry implements DomainRegistryContract
{
    public function __construct(private Application $app, private ConsoleKernel $artisan)
    {
    }

    public function add(string $host, array $values): void
    {
        $quoted = array_map(fn ($value) => TenantNaming::envValue((string) $value), $values);

        $this->call('domain:add', ['domain' => $host, '--domain_values' => json_encode($quoted)]);
        $this->forgetCachedBootstrap($host);
    }

    public function remove(string $host): void
    {
        $this->call('domain:remove', ['domain' => $host, '--force' => true]);
        $this->forgetCachedBootstrap($host);
    }

    /**
     * Cached config, routes and events of the domain (`php artisan optimize --domain=<host>`)
     * were built from the previous env file: drop them, so the domain reads the new file until
     * they are cached again (optimize.sh).
     */
    public function forgetCachedBootstrap(string $host): void
    {
        $sanitized = function_exists('domain_sanitized') ? domain_sanitized($host) : str_replace('.', '_', $host);
        foreach (['config', 'routes', 'events'] as $name) {
            $file = $this->app->bootstrapPath('cache/' . $name . '-' . $sanitized . '.php');
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function isRegistered(string $host): bool
    {
        $domains = (array) config('domain.domains', []);

        return $host !== ''
            && (array_key_exists($host, $domains) || in_array($host, $domains, true))
            && is_file($this->envFilePath($host));
    }

    public function storagePath(string $host): string
    {
        if (method_exists($this->app, 'exactDomainStoragePath')) {
            return $this->app->exactDomainStoragePath($host);
        }

        return storage_path(str_replace('.', '_', $host));
    }

    public function envFilePath(string $host): string
    {
        return rtrim($this->app->environmentPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env.' . $host;
    }

    private function call(string $command, array $parameters): void
    {
        $status = $this->artisan->call($command, $parameters);
        if ($status !== 0) {
            throw new RuntimeException("{$command} failed: " . trim($this->artisan->output()));
        }
    }
}
