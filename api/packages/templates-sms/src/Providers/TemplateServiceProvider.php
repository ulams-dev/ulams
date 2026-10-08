<?php

namespace Ulams\TemplatesSms\Providers;

use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesSms\Core\SmsChannel;
use Ulams\TemplatesSms\Core\UserVariables;
use Illuminate\Support\ServiceProvider;

class TemplateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Template::register(ManuallyTriggeredEvent::class, SmsChannel::class, UserVariables::class);
    }
}
