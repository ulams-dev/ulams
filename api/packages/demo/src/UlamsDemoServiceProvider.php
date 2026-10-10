<?php

namespace Ulams\Demo;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Ulams\Demo\Console\ResetDemoCommand;
use Ulams\Demo\Console\SeedDemoCommand;
use Ulams\Demo\Services\Contracts\DemoServiceContract;
use Ulams\Demo\Services\DemoService;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\Services\Contracts\AdministrableConfigServiceContract;

/**
 * Demo mode of a tenant: password-less login and an hourly reset. Everything except the
 * commands is registered only when DEMO_MODE=true.
 */
class UlamsDemoServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_demo';

    public $singletons = [
        DemoServiceContract::class => DemoService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ResetDemoCommand::class,
                SeedDemoCommand::class,
            ]);
            $this->publishes([
                __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], self::CONFIG_KEY . '.config');
        }

        if (!config(self::CONFIG_KEY . '.enabled')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->registerPublicConfig();
        $this->scheduleReset();
    }

    /**
     * `GET /api/config` → `ulams_demo: {enabled, front_url, admin_url}`; the front and the
     * admin already load it at boot.
     */
    private function registerPublicConfig(): void
    {
        if (!$this->app->bound(AdministrableConfigServiceContract::class)) {
            return;
        }

        foreach (['enabled', 'front_url', 'admin_url'] as $key) {
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.' . $key, ['nullable'], true, true);
        }
    }

    /**
     * The scheduler runs `schedule:run --domain=<host>` for every domain (scheduler.sh), so the
     * reset is scheduled exactly on the domains whose env has DEMO_MODE=true. The scheduled
     * command does not inherit `--domain`, hence it is passed explicitly.
     */
    private function scheduleReset(): void
    {
        if (!$this->app->runningInConsole() || !config(self::CONFIG_KEY . '.reset.schedule')) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // a flag goes in as a bare entry: Schedule::command() renders `'--force' => true` as `--force=1`,
            // which Symfony rejects for an option without a value (the reset then never ran)
            $parameters = ['--force'];
            $domain = method_exists($this->app, 'domain') ? $this->app->domain() : null;
            if (is_string($domain) && $domain !== '') {
                $parameters['--domain'] = $domain;
            }

            $schedule->command(ResetDemoCommand::class, $parameters)
                ->cron((string) config(self::CONFIG_KEY . '.reset.cron', '0 * * * *'))
                ->withoutOverlapping(120)
                ->runInBackground();
        });
    }
}
