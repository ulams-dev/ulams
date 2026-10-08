<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\AssignWithoutAccount\Events\AssignToProduct;
use Ulams\AssignWithoutAccount\Events\AssignToProductable;
use Ulams\AssignWithoutAccount\Events\UnassignProduct;
use Ulams\AssignWithoutAccount\Events\UnassignProductable;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\AssignWithoutAccount\AssignToProductableVariables;
use Ulams\TemplatesEmail\AssignWithoutAccount\AssignToProductVariables;
use Ulams\TemplatesEmail\AssignWithoutAccount\UnassignProductableVariables;
use Ulams\TemplatesEmail\AssignWithoutAccount\UnassignProductVariables;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

class AssignWithoutAccountTemplatesEventServiceProvider extends EventServiceProvider
{
    public function boot()
    {
        Template::register(
            AssignToProduct::class,
            EmailChannel::class,
            AssignToProductVariables::class
        );

        Template::register(
            AssignToProductable::class,
            EmailChannel::class,
            AssignToProductableVariables::class
        );

        Template::register(
            UnassignProduct::class,
            EmailChannel::class,
            UnassignProductVariables::class
        );

        Template::register(
            UnassignProductable::class,
            EmailChannel::class,
            UnassignProductableVariables::class);
    }
}
