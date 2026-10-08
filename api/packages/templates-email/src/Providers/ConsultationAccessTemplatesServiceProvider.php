<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\ConsultationAccess\Events\ConsultationAccessEnquiryAdminCreatedEvent;
use Ulams\ConsultationAccess\Events\ConsultationAccessEnquiryApprovedEvent;
use Ulams\ConsultationAccess\Events\ConsultationAccessEnquiryDisapprovedEvent;
use Ulams\TemplatesEmail\ConsultationAccess\ConsultationAccessEnquiryAdminCreatedVariables;
use Ulams\TemplatesEmail\ConsultationAccess\ConsultationAccessEnquiryApprovedVariables;
use Ulams\TemplatesEmail\ConsultationAccess\ConsultationAccessEnquiryDisapprovedVariables;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;
use Ulams\Templates\Facades\Template;

class ConsultationAccessTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ConsultationAccessEnquiryAdminCreatedEvent::class, EmailChannel::class, ConsultationAccessEnquiryAdminCreatedVariables::class);
        Template::register(ConsultationAccessEnquiryDisapprovedEvent::class, EmailChannel::class, ConsultationAccessEnquiryDisapprovedVariables::class);
        Template::register(ConsultationAccessEnquiryApprovedEvent::class, EmailChannel::class, ConsultationAccessEnquiryApprovedVariables::class);
    }
}
