<?php

namespace Ulams\MailerLite;

use Ulams\MailerLite\Providers\EventServiceProvider;
use Ulams\MailerLite\Providers\SettingsServiceProvider;
use Ulams\MailerLite\Services\Contracts\MailerLiteServiceContract;
use Ulams\MailerLite\Services\MailerLiteService;
use Illuminate\Support\ServiceProvider;

class UlamsMailerLiteServiceProvider extends ServiceProvider
{
    public $singletons = [
        MailerLiteServiceContract::class => MailerLiteService::class,
    ];

    public function boot()
    {
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php',
            'ulams_mailer_lite'
        );

        $this->app->register(SettingsServiceProvider::class)->booted(function () {
            $this->app->register(EventServiceProvider::class);
        });
    }
}
