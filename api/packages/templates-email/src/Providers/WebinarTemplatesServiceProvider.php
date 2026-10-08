<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Webinar\ReminderAboutTermVariables;
use Ulams\TemplatesEmail\Webinar\WebinarTrainerAssignedVariables;
use Ulams\TemplatesEmail\Webinar\WebinarTrainerUnassignedVariables;
use Ulams\Webinar\Events\ReminderAboutTerm;
use Ulams\Webinar\Events\WebinarTrainerAssigned;
use Ulams\Webinar\Events\WebinarTrainerUnassigned;
use Illuminate\Support\ServiceProvider;
use Ulams\Templates\Facades\Template;

class WebinarTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ReminderAboutTerm::class, EmailChannel::class, ReminderAboutTermVariables::class);
        Template::register(WebinarTrainerAssigned::class, EmailChannel::class, WebinarTrainerAssignedVariables::class);
        Template::register(WebinarTrainerUnassigned::class, EmailChannel::class, WebinarTrainerUnassignedVariables::class);
    }
}
