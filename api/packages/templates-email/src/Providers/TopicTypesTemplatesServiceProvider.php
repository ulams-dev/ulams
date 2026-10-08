<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\TopicTypes\TopicTypeChangedVariables;
use EscolaLms\TopicTypes\Events\TopicTypeChanged;
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
