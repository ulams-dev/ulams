<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\CourseAccess\Events\CourseAccessEnquiryAdminCreatedEvent;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\CourseAccess\CourseAccessEnquiryAdminCreatedVariables;
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
