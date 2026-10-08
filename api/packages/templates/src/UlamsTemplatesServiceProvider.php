<?php

namespace Ulams\Templates;

use Ulams\Templates\AuthServiceProvider;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Repository\Contracts\TemplateRepositoryContract;
use Ulams\Templates\Repository\TemplateRepository;
use Ulams\Templates\Services\Contracts\EventServiceContract;
use Ulams\Templates\Services\Contracts\TemplateChannelServiceContract;
use Ulams\Templates\Services\Contracts\TemplateEventServiceContract;
use Ulams\Templates\Services\Contracts\TemplateServiceContract;
use Ulams\Templates\Services\Contracts\TemplateVariablesServiceContract;
use Ulams\Templates\Services\EventService;
use Ulams\Templates\Services\TemplateChannelService;
use Ulams\Templates\Services\TemplateEventService;
use Ulams\Templates\Services\TemplateService;
use Ulams\Templates\Services\TemplateVariablesService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsTemplatesServiceProvider extends ServiceProvider
{
    public $singletons = [
        TemplateChannelServiceContract::class => TemplateChannelService::class,
        TemplateEventServiceContract::class => TemplateEventService::class,
        TemplateRepositoryContract::class => TemplateRepository::class,
        TemplateServiceContract::class => TemplateService::class,
        TemplateVariablesServiceContract::class => TemplateVariablesService::class,
        EventServiceContract::class => EventService::class,
    ];

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);

        Event::listen('Ulams*', function ($eventName, array $data) {
            app(TemplateEventListener::class)->handle($data[0]);
        });
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'template');
        $this->extendResources();
    }

    private function extendResources(): void
    {
        if (!class_exists(\Ulams\Auth\UlamsAuthServiceProvider::class)) {
            return;
        }

        \Ulams\Auth\Http\Resources\UserResource::extend(fn($thisObj) => [
            'notification_channels' => json_decode($thisObj->notification_channels),
        ]);

        \Ulams\Auth\Dtos\UserUpdateDto::extendConstructor([
            'notification_channels' => fn ($request) => json_encode($request->input('notification_channels')),
        ]);

        \Ulams\Auth\Dtos\UserUpdateDto::extendToArray([
            'notification_channels' => fn ($thisObj) => $thisObj->notification_channels
        ]);
    }
}
