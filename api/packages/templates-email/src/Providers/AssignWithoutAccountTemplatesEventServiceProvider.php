<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\AssignWithoutAccount\Events\AssignToProduct;
use EscolaLms\AssignWithoutAccount\Events\AssignToProductable;
use EscolaLms\AssignWithoutAccount\Events\UnassignProduct;
use EscolaLms\AssignWithoutAccount\Events\UnassignProductable;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\AssignWithoutAccount\AssignToProductableVariables;
use EscolaLms\TemplatesEmail\AssignWithoutAccount\AssignToProductVariables;
use EscolaLms\TemplatesEmail\AssignWithoutAccount\UnassignProductableVariables;
use EscolaLms\TemplatesEmail\AssignWithoutAccount\UnassignProductVariables;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
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
