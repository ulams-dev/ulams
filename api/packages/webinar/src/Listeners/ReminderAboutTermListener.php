<?php

namespace Ulams\Webinar\Listeners;

use Ulams\Webinar\Events\ReminderAboutTerm;
use Ulams\Webinar\Services\Contracts\WebinarServiceContract;

class ReminderAboutTermListener
{
    private WebinarServiceContract $webinarServiceContract;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(
        WebinarServiceContract $webinarServiceContract
    ) {
        $this->webinarServiceContract = $webinarServiceContract;
    }

    /**
     * Handle the event.
     *
     * @param  ReminderAboutTerm  $event
     * @return void
     */
    public function handle(ReminderAboutTerm $event)
    {
        $this->webinarServiceContract->setReminderStatus($event->getWebinar(), $event->getStatus());
    }
}
