<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Payments\Events\PaymentCancelled;
use EscolaLms\Payments\Events\PaymentFailed;
use EscolaLms\Payments\Events\PaymentRegistered;
use EscolaLms\Payments\Events\PaymentSuccess;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Payments\PaymentCanceledVariables;
use EscolaLms\TemplatesEmail\Payments\PaymentFailedVariables;
use EscolaLms\TemplatesEmail\Payments\PaymentRegisteredVariables;
use EscolaLms\TemplatesEmail\Payments\PaymentSuccessVariables;
use Illuminate\Support\ServiceProvider;

class PaymentsTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(
            PaymentRegistered::class,
            EmailChannel::class,
            PaymentRegisteredVariables::class
        );
        Template::register(
            PaymentFailed::class,
            EmailChannel::class,
            PaymentFailedVariables::class
        );
        Template::register(
            PaymentSuccess::class,
            EmailChannel::class,
            PaymentSuccessVariables::class
        );
        Template::register(
            PaymentCancelled::class,
            EmailChannel::class,
            PaymentCanceledVariables::class
        );
    }
}
