<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Tasks\Events\TaskAssignedEvent;
use Ulams\Tasks\Events\TaskCompleteRequestEvent;
use Ulams\Tasks\Events\TaskCompleteUserConfirmationEvent;
use Ulams\Tasks\Events\TaskIncompleteEvent;
use Ulams\Tasks\Events\TaskNoteCreatedEvent;
use Ulams\Tasks\Events\TaskOverdueEvent;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Tasks\TaskAssignedVariables;
use Ulams\TemplatesEmail\Tasks\TaskCompleteRequestVariables;
use Ulams\TemplatesEmail\Tasks\TaskCompleteUserConfirmationVariables;
use Ulams\TemplatesEmail\Tasks\TaskIncompleteVariables;
use Ulams\TemplatesEmail\Tasks\TaskNoteCreatedVariables;
use Ulams\TemplatesEmail\Tasks\TaskOverdueVariables;
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
