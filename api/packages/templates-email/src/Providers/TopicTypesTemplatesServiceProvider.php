<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\TopicTypes\TopicTypeChangedVariables;
use Ulams\TopicTypes\Events\TopicTypeChanged;
use Illuminate\Support\ServiceProvider;

class TopicTypesTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(
            TopicTypeChanged::class,
            EmailChannel::class,
            TopicTypeChangedVariables::class
        );
    }
}
