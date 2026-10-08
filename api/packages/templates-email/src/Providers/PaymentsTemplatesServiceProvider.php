<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Payments\Events\PaymentCancelled;
use Ulams\Payments\Events\PaymentFailed;
use Ulams\Payments\Events\PaymentRegistered;
use Ulams\Payments\Events\PaymentSuccess;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Payments\PaymentCanceledVariables;
use Ulams\TemplatesEmail\Payments\PaymentFailedVariables;
use Ulams\TemplatesEmail\Payments\PaymentRegisteredVariables;
use Ulams\TemplatesEmail\Payments\PaymentSuccessVariables;
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
