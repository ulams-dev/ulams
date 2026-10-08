<?php

namespace Ulams\Permissions;

use Illuminate\Support\ServiceProvider;
use Ulams\Permissions\AuthServiceProvider;
use Ulams\Permissions\Services\Contracts\PermissionsServiceContract;

use Ulams\Permissions\Services\PermissionsService;


/**
 * SWAGGER_VERSION
 */

class UlamsPermissionsServiceProvider extends ServiceProvider
{
    public $singletons = [
        PermissionsServiceContract::class => PermissionsService::class,
    ];

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'permission');
    }
}
