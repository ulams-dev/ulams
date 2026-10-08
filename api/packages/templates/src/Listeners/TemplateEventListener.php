<?php

namespace EscolaLms\Templates\Listeners;

use EscolaLms\Templates\Events\EventWrapper;
use EscolaLms\Templates\Facades\Template;

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
