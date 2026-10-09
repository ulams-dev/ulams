<?php

namespace Ulams\Tenancy;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Ulams\Tenancy\Console\CreateTenantCommand;
use Ulams\Tenancy\Console\DeleteTenantCommand;
use Ulams\Tenancy\Console\ExportH5PServiceConfigCommand;
use Ulams\Tenancy\Console\ListTenantsCommand;
use Ulams\Tenancy\Console\ScheduleLoopCommand;
use Ulams\Tenancy\Console\SeedTenantDemoCommand;
use Ulams\Tenancy\Console\SetTenantEnvCommand;
use Ulams\Tenancy\Console\RecreateViewsCommand;
use Ulams\Tenancy\Console\SyncTenantEnvCommand;
use Ulams\Tenancy\Console\UpgradeCommand;
use Ulams\Tenancy\Http\Middleware\RejectUnknownHost;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\H5PServiceConfigExporter;
use Ulams\Tenancy\Services\MultidomainRegistry;
use Ulams\Tenancy\Services\PostgresDatabaseProvisioner;
use Ulams\Tenancy\Services\ProcessTenantCommandRunner;
use Ulams\Tenancy\Services\S3BucketProvisioner;
use Ulams\Tenancy\Upgrade\DefaultUpgradeSteps;

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
        // in register(), so steps of other packages (registered when they boot) come after these
        DefaultUpgradeSteps::register();

        $this->app->singleton(
            BucketProvisionerContract::class,
            fn () => S3BucketProvisioner::fromConfig(config(self::CONFIG_KEY . '.s3', []))
        );
        $this->app->singleton(TenantCommandRunnerContract::class, fn ($app) => new ProcessTenantCommandRunner(
            $app->basePath(),
            (string) config(self::CONFIG_KEY . '.php_binary', 'php'),
            (int) config(self::CONFIG_KEY . '.process_timeout', 900),
        ));
        $this->app->singleton(H5PServiceConfigExporter::class, fn ($app) => new H5PServiceConfigExporter(
            $app->make(\Illuminate\Filesystem\Filesystem::class),
            rtrim($app->environmentPath(), DIRECTORY_SEPARATOR),
            storage_path(),
            config(self::CONFIG_KEY . '.h5p_service_config_dir'),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

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
            SetTenantEnvCommand::class,
                SeedTenantDemoCommand::class,
                ExportH5PServiceConfigCommand::class,
                ScheduleLoopCommand::class,
                UpgradeCommand::class,
                RecreateViewsCommand::class,
            ]);
            $this->publishes([
                __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], self::CONFIG_KEY . '.config');
        }
    }
}
