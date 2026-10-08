<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\TopicTypeProject\ProjectSolutionCreatedVariables;
use Ulams\TopicTypeProject\Events\ProjectSolutionCreatedEvent;
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
