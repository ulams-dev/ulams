<?php

namespace EscolaLms\Permissions;

use Illuminate\Support\ServiceProvider;
use EscolaLms\Permissions\AuthServiceProvider;
use EscolaLms\Permissions\Services\Contracts\PermissionsServiceContract;

use EscolaLms\Permissions\Services\PermissionsService;


/**
 * SWAGGER_VERSION
 */

class EscolaLmsPermissionsServiceProvider extends ServiceProvider
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
