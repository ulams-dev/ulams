<?php

namespace Ulams\Auth;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Ulams\Auth\Console\Commands\CreateAdminCommand;
use Ulams\Auth\Console\Commands\ExportTokenScopesCommand;
use Ulams\Auth\Console\Commands\PruneAgentAuditCommand;
use Ulams\Auth\Console\Commands\PruneDeviceAuthorizationsCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Ulams\Auth\Services\Contracts\DeviceAuthorizationServiceContract;
use Ulams\Auth\Services\DeviceAuthorizationService;
use Ulams\Auth\Http\Middleware\EnforceTokenScopes;
use Ulams\Auth\Http\Middleware\RecordAgentAudit;
use Ulams\Auth\Http\Middleware\StripTokenPrefix;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Services\PersonalAccessTokenService;
use Ulams\Auth\Support\TokenScopes;
use Ulams\Auth\Providers\AuthServiceProvider;
use Ulams\Auth\Providers\EventServiceProvider;
use Ulams\Auth\Providers\SettingsServiceProvider;
use Ulams\Auth\Repositories\Contracts\PreUserRepositoryContract;
use Ulams\Auth\Repositories\Contracts\SocialAccountRepositoryContract;
use Ulams\Auth\Repositories\Contracts\UserGroupRepositoryContract;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\Auth\Repositories\PreUserRepository;
use Ulams\Auth\Repositories\SocialAccountRepository;
use Ulams\Auth\Repositories\UserGroupRepository;
use Ulams\Auth\Repositories\UserRepository;
use Ulams\Auth\Services\AuthService;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Auth\Services\Contracts\SocialAccountServiceContract;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Auth\Services\SocialAccountService;
use Ulams\Auth\Services\UserGroupService;
use Ulams\Auth\Services\UserService;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\SocialiteServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsAuthServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_auth';

    public const SERVICES = [
        AuthServiceContract::class => AuthService::class,
        UserGroupServiceContract::class => UserGroupService::class,
        UserServiceContract::class => UserService::class,
        SocialAccountServiceContract::class => SocialAccountService::class,
        PersonalAccessTokenServiceContract::class => PersonalAccessTokenService::class,
        DeviceAuthorizationServiceContract::class => DeviceAuthorizationService::class,
    ];

    public const REPOSITORIES = [
        UserGroupRepositoryContract::class => UserGroupRepository::class,
        UserRepositoryContract::class => UserRepository::class,
        PreUserRepositoryContract::class => PreUserRepository::class,
        SocialAccountRepositoryContract::class => SocialAccountRepository::class,
    ];

    public array $bindings = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'auth');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/auth'),
        ]);
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'user');

        $this->app->register(EventServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(ModelFieldsServiceProvider::class);
        $this->app->register(SocialiteServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // scoped personal access tokens (ADR 0074): Passport only keeps scopes it knows
        Passport::tokensCan(array_fill_keys(TokenScopes::passportScopes(), 'API token scope'));
        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(StripTokenPrefix::class);
        }
        // package routes are not in the `api` group: attach on match (audit wraps the scope check)
        Event::listen(RouteMatched::class, fn (RouteMatched $e) => $e->route->middleware([RecordAgentAudit::class, EnforceTokenScopes::class]));
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('ulams:auth:prune-agent-audit')->daily();
            $schedule->command('ulams:auth:prune-device-authorizations')->hourly();
        });
        // device approval: 5 requests per minute per signed-in user (lookups and answers share the bucket)
        RateLimiter::for('ulams-device-approve', fn (Request $r) => Limit::perMinute((int) config(self::CONFIG_KEY . '.device_approve_per_minute', 5))
            ->by('device-approve:' . ($r->user('api')?->getAuthIdentifier() ?? $r->ip())));

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
        $this->commands([
            CreateAdminCommand::class,
            ExportTokenScopesCommand::class,
            PruneAgentAuditCommand::class,
            PruneDeviceAuthorizationsCommand::class,
        ]);
    }
}
