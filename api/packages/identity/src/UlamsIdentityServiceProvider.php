<?php

namespace Ulams\Identity;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;

/**
 * Identity (Phase 6.3): single sign-on per tenant. OpenID Connect login with per-tenant providers, account
 * creation and strict linking, and a hash-chained audit trail. SAML, SCIM and HRIS import follow in later
 * milestones (docs/plans/phase-6-identity.md, ADR 0120).
 */
class UlamsIdentityServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_identity';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsAuthServiceProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for('ulams-identity', fn (Request $request) => Limit::perMinute((int) config('ulams_identity.public_rate_limit'))->by('identity:' . $request->ip()));
        RateLimiter::for('ulams-identity-admin', fn (Request $request) => Limit::perMinute((int) config('ulams_identity.admin_rate_limit'))->by('identity-admin:' . ($request->user()?->getKey() ?? $request->ip())));

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
    }
}
