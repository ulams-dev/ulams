<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\CourseAccess\Events\CourseAccessEnquiryAdminCreatedEvent;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\CourseAccess\CourseAccessEnquiryAdminCreatedVariables;
use Illuminate\Support\ServiceProvider;

class CourseAccessTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(
            CourseAccessEnquiryAdminCreatedEvent::class,
            EmailChannel::class,
            CourseAccessEnquiryAdminCreatedVariables::class
        );
    }
}
