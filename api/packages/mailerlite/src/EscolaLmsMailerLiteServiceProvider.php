<?php

namespace EscolaLms\MailerLite;

use EscolaLms\MailerLite\Providers\EventServiceProvider;
use EscolaLms\MailerLite\Providers\SettingsServiceProvider;
use EscolaLms\MailerLite\Services\Contracts\MailerLiteServiceContract;
use EscolaLms\MailerLite\Services\MailerLiteService;
use Illuminate\Support\ServiceProvider;

class EscolaLmsMailerLiteServiceProvider extends ServiceProvider
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
            'escolalms_mailer_lite'
        );

        $this->app->register(SettingsServiceProvider::class)->booted(function () {
            $this->app->register(EventServiceProvider::class);
        });
    }
}
