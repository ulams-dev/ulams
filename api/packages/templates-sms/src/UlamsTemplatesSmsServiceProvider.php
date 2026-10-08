<?php

namespace Ulams\TemplatesSms;

use Ulams\Consultations\UlamsConsultationsServiceProvider;
use Ulams\TemplatesSms\Enums\ConfigEnum;
use Ulams\TemplatesSms\Providers\ConsultationTemplatesServiceProvider;
use Ulams\TemplatesSms\Providers\SettingsServiceProvider;
use Ulams\TemplatesSms\Providers\TemplateServiceProvider;
use Illuminate\Support\ServiceProvider;

class UlamsTemplatesSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sms.php', ConfigEnum::CONFIG_KEY);

        $this->app->register(SettingsServiceProvider::class);

        if (class_exists(UlamsConsultationsServiceProvider::class)) {
            $this->app->register(ConsultationTemplatesServiceProvider::class);
        }

        $this->app->register(TemplateServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sms.php' => config_path(ConfigEnum::CONFIG_KEY . '.php'),
        ], ConfigEnum::CONFIG_KEY . '.config');
    }
}
