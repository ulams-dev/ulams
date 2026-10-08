<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Tasks\Events\TaskAssignedEvent;
use EscolaLms\Tasks\Events\TaskCompleteRequestEvent;
use EscolaLms\Tasks\Events\TaskCompleteUserConfirmationEvent;
use EscolaLms\Tasks\Events\TaskIncompleteEvent;
use EscolaLms\Tasks\Events\TaskNoteCreatedEvent;
use EscolaLms\Tasks\Events\TaskOverdueEvent;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Tasks\TaskAssignedVariables;
use EscolaLms\TemplatesEmail\Tasks\TaskCompleteRequestVariables;
use EscolaLms\TemplatesEmail\Tasks\TaskCompleteUserConfirmationVariables;
use EscolaLms\TemplatesEmail\Tasks\TaskIncompleteVariables;
use EscolaLms\TemplatesEmail\Tasks\TaskNoteCreatedVariables;
use EscolaLms\TemplatesEmail\Tasks\TaskOverdueVariables;
use Illuminate\Support\ServiceProvider;

class TaskTemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Template::register(
            TaskAssignedEvent::class,
            EmailChannel::class,
            TaskAssignedVariables::class
        );

        Template::register(
            TaskCompleteUserConfirmationEvent::class,
            EmailChannel::class,
            TaskCompleteUserConfirmationVariables::class
        );

        Template::register(
            TaskCompleteRequestEvent::class,
            EmailChannel::class,
            TaskCompleteRequestVariables::class
        );

        Template::register(
            TaskOverdueEvent::class,
            EmailChannel::class,
            TaskOverdueVariables::class
        );

        Template::register(
            TaskIncompleteEvent::class,
            EmailChannel::class,
            TaskIncompleteVariables::class
        );

        Template::register(
            TaskNoteCreatedEvent::class,
            EmailChannel::class,
            TaskNoteCreatedVariables::class
        );
    }
}
