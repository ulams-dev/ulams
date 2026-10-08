<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Webinar\ReminderAboutTermVariables;
use EscolaLms\TemplatesEmail\Webinar\WebinarTrainerAssignedVariables;
use EscolaLms\TemplatesEmail\Webinar\WebinarTrainerUnassignedVariables;
use EscolaLms\Webinar\Events\ReminderAboutTerm;
use EscolaLms\Webinar\Events\WebinarTrainerAssigned;
use EscolaLms\Webinar\Events\WebinarTrainerUnassigned;
use Illuminate\Support\ServiceProvider;
use EscolaLms\Templates\Facades\Template;

class WebinarTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ReminderAboutTerm::class, EmailChannel::class, ReminderAboutTermVariables::class);
        Template::register(WebinarTrainerAssigned::class, EmailChannel::class, WebinarTrainerAssignedVariables::class);
        Template::register(WebinarTrainerUnassigned::class, EmailChannel::class, WebinarTrainerUnassignedVariables::class);
    }
}
