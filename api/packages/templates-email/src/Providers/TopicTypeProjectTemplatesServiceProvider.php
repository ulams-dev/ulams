<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\TopicTypeProject\ProjectSolutionCreatedVariables;
use EscolaLms\TopicTypeProject\Events\ProjectSolutionCreatedEvent;
use Illuminate\Support\ServiceProvider;

class TopicTypeProjectTemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Template::register(
            ProjectSolutionCreatedEvent::class,
            EmailChannel::class,
            ProjectSolutionCreatedVariables::class
        );
    }
}
