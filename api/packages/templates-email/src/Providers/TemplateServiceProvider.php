<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\UserVariables;
use Illuminate\Support\ServiceProvider;

class TemplateServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(
            ManuallyTriggeredEvent::class,
            EmailChannel::class,
            UserVariables::class
        );
    }
}
