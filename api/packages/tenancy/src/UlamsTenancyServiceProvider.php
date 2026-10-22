<?php

namespace Ulams\Tenancy;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Ulams\Core\Support\EntryFronts;
use Ulams\Tenancy\Console\CreateTenantCommand;
use Ulams\Tenancy\Console\DeleteTenantCommand;
use Ulams\Tenancy\Console\EnsureTenantBucketsCommand;
use Ulams\Tenancy\Console\EntryPointGatewayCommand;
use Ulams\Tenancy\Console\PublishEntryHostsCommand;
use Ulams\Tenancy\Console\ReconcileEntryHostsCommand;
use Ulams\Tenancy\Console\ExportH5PServiceConfigCommand;
use Ulams\Tenancy\Console\ListTenantsCommand;
use Ulams\Tenancy\Console\ScheduleLoopCommand;
use Ulams\Tenancy\Console\SeedTenantDemoCommand;
use Ulams\Tenancy\Console\SetTenantEnvCommand;
use Ulams\Tenancy\Console\RecreateViewsCommand;
use Ulams\Tenancy\Console\SyncTenantEnvCommand;
use Ulams\Tenancy\Console\UpgradeCommand;
use Ulams\Tenancy\Console\WorkOnceCommand;
use Ulams\Tenancy\Http\Middleware\RejectUnknownHost;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\EntryHostPublisherContract;
use Ulams\Tenancy\Services\Contracts\EntryPointGatewayContract;
use Ulams\Tenancy\Services\Contracts\StorageIdentityProvisionerContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\EntryHostPublisher;
use Ulams\Tenancy\Services\EntryHostStore;
use Ulams\Tenancy\Services\EntryPointService;
use Ulams\Tenancy\Services\H5PServiceConfigExporter;
use Ulams\Tenancy\Services\ManualDatabaseProvisioner;
use Ulams\Tenancy\Services\NullStorageIdentityProvisioner;
use Ulams\Tenancy\Services\MultidomainRegistry;
use Ulams\Tenancy\Services\PlatformEntryPointGateway;
use Ulams\Tenancy\Services\PostgresDatabaseProvisioner;
use Ulams\Tenancy\Services\ProcessTenantCommandRunner;
use Ulams\Tenancy\Services\S3BucketProvisioner;
use Ulams\Tenancy\Services\SeaweedFsIdentityProvisioner;
use Ulams\Tenancy\Support\TenantContext;
use Ulams\Tenancy\Upgrade\DefaultUpgradeSteps;

class UlamsTenancyServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_tenancy';

    public $singletons = [
        DomainRegistryContract::class => MultidomainRegistry::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        // in register(), so steps of other packages (registered when they boot) come after these
        DefaultUpgradeSteps::register();

        $this->app->singleton(DatabaseProvisionerContract::class, fn ($app) => config(self::CONFIG_KEY . '.database_provisioner') === 'manual'
            ? $app->make(ManualDatabaseProvisioner::class)
            : $app->make(PostgresDatabaseProvisioner::class));
        $this->app->singleton(
            BucketProvisionerContract::class,
            fn () => S3BucketProvisioner::fromConfig(config(self::CONFIG_KEY . '.s3', []))
        );
        $this->app->singleton(StorageIdentityProvisionerContract::class, function () {
            $driver = strtolower((string) config(self::CONFIG_KEY . '.storage_identity.driver', 'none'));

            return match ($driver) {
                'none', '' => new NullStorageIdentityProvisioner(),
                'seaweedfs' => SeaweedFsIdentityProvisioner::fromConfig(array_merge(
                    (array) config(self::CONFIG_KEY . '.s3', []),
                    array_filter(['endpoint' => config(self::CONFIG_KEY . '.storage_identity.endpoint')])
                )),
                default => throw new \InvalidArgumentException("TENANCY_S3_IDENTITY_DRIVER must be seaweedfs or none, got '{$driver}'."),
            };
        });
        $this->app->singleton(TenantCommandRunnerContract::class, fn ($app) => new ProcessTenantCommandRunner(
            $app->basePath(),
            (string) config(self::CONFIG_KEY . '.php_binary', 'php'),
            (int) config(self::CONFIG_KEY . '.process_timeout', 900),
        ));
        // a tenant process reaches the platform table through a platform child process; the platform (and the
        // tests) use the service itself
        $this->app->singleton(EntryHostPublisherContract::class, fn ($app) => new EntryHostPublisher($app->make(TenantCommandRunnerContract::class)));
        $this->app->singleton(EntryHostPublisher::class, fn ($app) => $app->make(EntryHostPublisherContract::class));
        $this->app->singleton(EntryHostStore::class);
        // one registry of entry hosts per request, shared with core (origin check, return URLs, frame ancestors, hand-off)
        $this->app->singleton(EntryFronts::class);
        $this->app->singleton(EntryPointService::class);
        $this->app->singleton(EntryPointGatewayContract::class, fn ($app) => TenantContext::isPlatform()
            ? $app->make(EntryPointService::class)
            : new PlatformEntryPointGateway($app->make(TenantCommandRunnerContract::class)));
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
        RateLimiter::for('ulams-entry-points-admin', fn (Request $request) => Limit::perMinute(60)->by('entry-points:' . ($request->user('api')?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('ulams-entry-resolve', fn (Request $request) => Limit::perMinute((int) config(self::CONFIG_KEY . '.entry_resolve_rate', 600))->by('entry-resolve:' . $request->ip()));
        RateLimiter::for('ulams-platform', fn (Request $request) => Limit::perMinute(60)->by('platform:' . ($request->user('api')?->getAuthIdentifier() ?? $request->ip())));

        // the academy's own active entry hosts are trusted origins, return URLs, frame ancestors and hand-off targets (ADR 0195)
        $this->app->make(EntryFronts::class)->addSource(fn () => $this->app->make(EntryHostStore::class)->origins());

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
                EnsureTenantBucketsCommand::class,
            SetTenantEnvCommand::class,
                SeedTenantDemoCommand::class,
                EntryPointGatewayCommand::class,
                PublishEntryHostsCommand::class,
                ReconcileEntryHostsCommand::class,
                ExportH5PServiceConfigCommand::class,
                ScheduleLoopCommand::class,
                WorkOnceCommand::class,
                UpgradeCommand::class,
                RecreateViewsCommand::class,
            ]);
            $this->publishes([
                __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], self::CONFIG_KEY . '.config');
            // academies whose copy of the entry hosts is out of date (a failed publication, a restored database)
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                if (TenantContext::isPlatform()) {
                    $schedule->command('ulams:entry-points:reconcile')->everyFiveMinutes()->withoutOverlapping();
                }
            });
        }
    }
}
