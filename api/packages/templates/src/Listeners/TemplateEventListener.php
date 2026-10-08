<?php

namespace Ulams\Templates\Listeners;

use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Facades\Template;

class TemplateEventListener
{
    public function handle(object $event)
    {
        $eventWrapper = new EventWrapper($event);
        if ($eventWrapper->user()) {
            // we only want to handle User related events (probably?)
            Template::handleEvent($eventWrapper);
        }
    }
}
