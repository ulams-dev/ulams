<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryAdminCreatedEvent;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryApprovedEvent;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryDisapprovedEvent;
use EscolaLms\TemplatesEmail\ConsultationAccess\ConsultationAccessEnquiryAdminCreatedVariables;
use EscolaLms\TemplatesEmail\ConsultationAccess\ConsultationAccessEnquiryApprovedVariables;
use EscolaLms\TemplatesEmail\ConsultationAccess\ConsultationAccessEnquiryDisapprovedVariables;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;
use EscolaLms\Templates\Facades\Template;

class ConsultationAccessTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ConsultationAccessEnquiryAdminCreatedEvent::class, EmailChannel::class, ConsultationAccessEnquiryAdminCreatedVariables::class);
        Template::register(ConsultationAccessEnquiryDisapprovedEvent::class, EmailChannel::class, ConsultationAccessEnquiryDisapprovedVariables::class);
        Template::register(ConsultationAccessEnquiryApprovedEvent::class, EmailChannel::class, ConsultationAccessEnquiryApprovedVariables::class);
    }
}
