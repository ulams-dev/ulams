<?php

namespace Ulams\Templates\Testing;

use Ulams\Core\Models\User;
use Ulams\Templates\Core\TemplatePreview;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Repository\Contracts\TemplateRepositoryContract;
use Ulams\Templates\Services\Contracts\TemplateChannelServiceContract;
use Ulams\Templates\Services\Contracts\TemplateEventServiceContract;
use Ulams\Templates\Services\Contracts\TemplateVariablesServiceContract;
use Ulams\Templates\Services\TemplateEventService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

class TemplateFake extends TemplateEventService implements TemplateEventServiceContract
{
    protected Collection $handledEvents;

    public function __construct(
        TemplateRepositoryContract $repository,
        TemplateVariablesServiceContract $variableService,
        TemplateChannelServiceContract $channelService
    ) {
        parent::__construct($repository, $variableService, $channelService);
        $this->handledEvents = new Collection();
    }

    public function setRegisteredEvents(array $events): void
    {
        $this->events = $events;
    }

    public function handleEvent(EventWrapper $event): void
    {
        if (!array_key_exists($event->eventClass(), $this->events)) {
            return;
        }

        foreach ($this->events[$event->eventClass()] as $channelClass => $variableClass) {
            $template = $this->getTemplateForEvent($event, $channelClass, $variableClass);

            if ($template && $template->is_valid) {
                $variables = $variableClass::variablesFromEvent($event);
                $sections = $template->generateContent($variables);

                if ($this->channelService->validateTemplateSections($channelClass, $sections)) {
                    $eventData = [
                        'eventClass' => $event->eventClass(),
                        'channelClass' => $channelClass,
                        'variableClass' => $variableClass,
                        'event' => $event,
                        'variables' => $variables,
                        'template_name' => $template->name,
                        'template_id' => $template->getKey(),
                        'sections' => $sections,
                    ];
                    $this->handledEvents->push($eventData);
                }
            }
        }
    }

    /**
     * @param string|callable $eventClassOrCallable
     * @param string $channelClass
     * @param string|null $variableClass
     * @return void
     */
    public function assertEventHandled($eventClassOrCallable, ?string $channelClass = null, ?string $variableClass = null): void
    {
        if (is_callable($eventClassOrCallable)) {
            $events = $this->handledEvents->filter($eventClassOrCallable);
        } else {
            $events = $this->handledEvents->filter(function ($handledEvent) use ($eventClassOrCallable, $channelClass, $variableClass) {
                return $handledEvent['event']->eventClass() === $eventClassOrCallable
                    && (empty($channelClass) || $handledEvent['channel'] === $channelClass)
                    && (empty($variableClass) || $handledEvent['variable'] === $variableClass);
            });
        }

        PHPUnit::assertTrue(
            $events->count() > 0,
            "Event was not handled properly."
        );
    }

    public function sendPreview(User $user, Template $template): TemplatePreview
    {
        $sections = $template->previewContent($user);
        $sent = true;
        return new TemplatePreview($user, $sections, $sent);
    }
}
