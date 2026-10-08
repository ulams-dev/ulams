<?php

namespace Ulams\TemplatesPdf\Providers;

use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Core\UserVariables;
use Illuminate\Support\ServiceProvider;

class UserTemplateServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ManuallyTriggeredEvent::class, PdfChannel::class, UserVariables::class);
    }
}
