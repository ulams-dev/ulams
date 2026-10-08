<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Templates\Events\ManuallyTriggeredEvent;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Core\UserVariables;
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
