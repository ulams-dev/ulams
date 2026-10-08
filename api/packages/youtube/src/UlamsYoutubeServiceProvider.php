<?php

namespace Ulams\Youtube;

use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Core\UlamsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Youtube\Services\AuthenticateService;
use Ulams\Youtube\Services\AuthService;
use Ulams\Youtube\Services\Contracts\AuthenticateServiceContract;
use Ulams\Youtube\Services\Contracts\AuthServiceContract;
use Ulams\Youtube\Services\Contracts\LiveStreamServiceContract;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use Ulams\Youtube\Services\LiveStreamService;
use Ulams\Youtube\Services\NullYoutubeService;
use Ulams\Youtube\Services\YoutubeService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */

class UlamsYoutubeServiceProvider extends ServiceProvider
{

    const CONFIG_KEY = 'youtube';

    public $singletons = [
        AuthServiceContract::class => AuthService::class,
        AuthenticateServiceContract::class => AuthenticateService::class,
        LiveStreamServiceContract::class => LiveStreamService::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }


    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/youtube.php',
            'youtube'
        );

        $this->app->register(UlamsSettingsServiceProvider::class);
        $this->app->register(UlamsServiceProvider::class);
        AdministrableConfig::registerConfig('services.youtube.refresh_token', ['nullable', 'string'], false);
        AdministrableConfig::registerConfig('services.youtube.client_id', ['nullable', 'string'], false);
        AdministrableConfig::registerConfig('services.youtube.client_secret', ['nullable', 'string'], false);
        AdministrableConfig::registerConfig('services.youtube.api_key', ['nullable', 'string'], false);
        AdministrableConfig::registerConfig('services.youtube.redirect_url', ['nullable', 'string'], false);
        Config::set('ulams_settings.use_database', true);

        $this->app->singleton(YoutubeServiceContract::class, function ($app) {
            $clientId = config('services.youtube.client_id');
            $clientSecret = config('services.youtube.client_secret');

            if (empty($clientId) || empty($clientSecret)) {
                return new NullYoutubeService();
            }

            return new YoutubeService($app->make(AuthenticateServiceContract::class), $app->make(LiveStreamServiceContract::class));
        });
    }
}
