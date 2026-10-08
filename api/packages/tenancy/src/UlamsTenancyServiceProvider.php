<?php

namespace Ulams\Tenancy;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Ulams\Tenancy\Console\CreateTenantCommand;
use Ulams\Tenancy\Console\DeleteTenantCommand;
use Ulams\Tenancy\Console\ListTenantsCommand;
use Ulams\Tenancy\Console\SeedTenantDemoCommand;
use Ulams\Tenancy\Console\SyncTenantEnvCommand;
use Ulams\Tenancy\Http\Middleware\RejectUnknownHost;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\MultidomainRegistry;
use Ulams\Tenancy\Services\PostgresDatabaseProvisioner;
use Ulams\Tenancy\Services\ProcessTenantCommandRunner;
use Ulams\Tenancy\Services\S3BucketProvisioner;

class UlamsTenancyServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_tenancy';

    public $singletons = [
        DatabaseProvisionerContract::class => PostgresDatabaseProvisioner::class,
        DomainRegistryContract::class => MultidomainRegistry::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->singleton(
            BucketProvisionerContract::class,
            fn () => S3BucketProvisioner::fromConfig(config(self::CONFIG_KEY . '.s3', []))
        );
        $this->app->singleton(TenantCommandRunnerContract::class, fn ($app) => new ProcessTenantCommandRunner(
            $app->basePath(),
            (string) config(self::CONFIG_KEY . '.php_binary', 'php'),
            (int) config(self::CONFIG_KEY . '.process_timeout', 900),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $kernel = $this->app->make(HttpKernel::class);
        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(RejectUnknownHost::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateTenantCommand::class,
                ListTenantsCommand::class,
                DeleteTenantCommand::class,
                SyncTenantEnvCommand::class,
                SeedTenantDemoCommand::class,
            ]);
            $this->publishes([
                __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], self::CONFIG_KEY . '.config');
        }
    }
}
